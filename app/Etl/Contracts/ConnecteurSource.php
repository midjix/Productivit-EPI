<?php

namespace App\Etl\Contracts;

use App\Enums\SourceImport;

/**
 * Contrat commun à tous les connecteurs (pattern Adaptateur).
 *
 * Chaque source externe a sa propre technologie (HTTP pour DSK, SQL pour EpiLogg) ;
 * l'EtlService ne manipule que ces abstractions et des DTO, jamais une réponse
 * HTTP ni une ligne SQL. Contrainte absolue : LECTURE SEULE sur les sources.
 */
interface ConnecteurSource
{
    public function source(): SourceImport;

    /** Vérifie que la source est joignable et authentifiée ; lève une exception sinon. */
    public function verifierConnexion(): void;
}
