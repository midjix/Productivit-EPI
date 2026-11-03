<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Service;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Contrôle du périmètre de l'écran Détail (et de son export) : salarié → UserPolicy,
 * service → ServicePolicy, tarif / Total EPI / activité interne → administrateur seul.
 * Renvoie le libellé du périmètre tel qu'affiché.
 */
trait AutorisePerimetreDetail
{
    protected function autoriserPerimetre(User $user, string $perimetre, ?int $id): string
    {
        switch ($perimetre) {
            case 'salarie':
                $cible = User::findOrFail($id);
                Gate::authorize('voirStatistiques', $cible);

                return $cible->name;
            case 'service':
                $service = Service::findOrFail($id);
                Gate::authorize('voirStatistiques', $service);

                return $service->nom;
            case 'tarif':
                abort_unless($user->estAdministrateur(), 403, __('epi.auth.droits_insuffisants'));
                $tarif = Tarif::findOrFail($id);

                return __('epi.detail.tarif', ['code' => $tarif->code]);
            default:
                abort_unless($user->estAdministrateur(), 403, __('epi.auth.droits_insuffisants'));

                return __('epi.detail.'.$perimetre);
        }
    }
}
