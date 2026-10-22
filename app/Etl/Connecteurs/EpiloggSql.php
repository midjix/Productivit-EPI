<?php

namespace App\Etl\Connecteurs;

use App\Enums\SourceImport;
use App\Etl\Contracts\ConnecteurEpilogg;
use App\Etl\Dto\PrestationEpilogg;
use App\Etl\Dto\SalarieEpilogg;
use App\Etl\Dto\TypeSuiviEpilogg;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Adaptateur SQL pour EpiLogg : lit la base de production du prestataire (CBC)
 * via la connexion `epilogg` (compte lecture seule + session READ ONLY).
 *
 * Schéma réel relevé le 22/10/2026 (MySQL 8.0) :
 *   benefits (prestations) --attend_id--> attends (dossiers) --type_id--> attend_types
 *   benefits --intervention_id--> interventions (type de prestation, libellé)
 *   benefits --user_id--> users (email = clé d'appariement)
 *   benefits.service_package_used = nombre de forfaits (decimal 8,2)
 *   benefits.travel_package_used  = forfait déplacement (0/1)
 *   benefits.appointment_date     = date de la prestation
 * Aucune colonne de bénéficiaire (customer_id) n'est sélectionnée : minimisation RGPD.
 */
class EpiloggSql implements ConnecteurEpilogg
{
    private const LOT = 1000;

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly string $connexion = 'epilogg',
    ) {}

    public function source(): SourceImport
    {
        return SourceImport::Epilogg;
    }

    public function verifierConnexion(): void
    {
        $etat = $this->conn()->selectOne('SELECT @@session.transaction_read_only AS ro, DATABASE() AS db');

        if ($etat === null || (int) $etat->ro !== 1) {
            throw new RuntimeException('La session EpiLogg n\'est pas en lecture seule : import refusé.');
        }
    }

    public function typesDeSuivi(): iterable
    {
        $lignes = $this->conn()->table('attend_types')
            ->select(['id', 'name', 'rate_name', 'minute_by_package', 'deleted_at'])
            ->orderBy('id')
            ->get();

        foreach ($lignes as $l) {
            yield new TypeSuiviEpilogg(
                id: (int) $l->id,
                libelle: trim((string) $l->name),
                rateName: $l->rate_name !== null ? trim((string) $l->rate_name) : null,
                minutesParForfait: $l->minute_by_package !== null ? (int) $l->minute_by_package : null,
                archive: $l->deleted_at !== null,
            );
        }
    }

    public function salaries(): iterable
    {
        $lignes = $this->conn()->table('users')
            ->select(['id', 'email', 'name', 'inactive', 'deleted_at'])
            ->orderBy('id')
            ->get();

        foreach ($lignes as $l) {
            yield new SalarieEpilogg(
                id: (int) $l->id,
                email: $l->email !== null ? trim((string) $l->email) : null,
                nom: $l->name !== null ? trim((string) $l->name) : null,
                actif: ! (bool) $l->inactive && $l->deleted_at === null,
            );
        }
    }

    public function prestations(CarbonInterface $du, CarbonInterface $au): iterable
    {
        $requete = $this->conn()->table('benefits as b')
            ->join('attends as a', 'a.id', '=', 'b.attend_id')
            ->leftJoin('interventions as i', 'i.id', '=', 'b.intervention_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->select([
                'b.id', 'b.user_id', 'u.email', 'b.appointment_date', 'a.type_id',
                'i.name as intervention', 'b.service_package_used', 'b.travel_package_used',
                'b.service_duration', 'b.travel_duration', 'b.updated_at',
            ])
            ->whereBetween('b.appointment_date', [
                $du->toImmutable()->startOfDay()->toDateTimeString(),
                $au->toImmutable()->endOfDay()->toDateTimeString(),
            ])
            ->orderBy('b.id');

        foreach ($requete->lazy(self::LOT) as $l) {
            yield new PrestationEpilogg(
                id: (int) $l->id,
                utilisateurId: (int) $l->user_id,
                email: $l->email !== null ? trim((string) $l->email) : null,
                date: CarbonImmutable::parse($l->appointment_date)->startOfDay(),
                typeSuiviId: (int) $l->type_id,
                typePrestation: $l->intervention !== null ? trim((string) $l->intervention) : null,
                quantite: number_format((float) ($l->service_package_used ?? 0), 2, '.', ''),
                forfaitDeplacement: (bool) $l->travel_package_used,
                minutesService: $l->service_duration !== null ? (int) $l->service_duration : null,
                minutesDeplacement: $l->travel_duration !== null ? (int) $l->travel_duration : null,
                modifieLe: $l->updated_at !== null ? CarbonImmutable::parse($l->updated_at) : null,
            );
        }
    }

    private function conn(): ConnectionInterface
    {
        return $this->db->connection($this->connexion);
    }
}
