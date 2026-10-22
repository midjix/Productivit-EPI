<?php

namespace App\Enums;

/**
 * Classification métier des compteurs DSK.
 *
 * DSK n'expose que des « comptes » numérotés (noAccount) : c'est la Fondation
 * qui décide, compte par compte, à quelle catégorie il correspond.
 *
 * Catégories issues des types d'heures DSK listés par la Fondation
 * (« Données app EPI.xlsx ») et de leur rôle dans la cascade de disponibilité :
 *   Conge       : congés annuels, congés exceptionnels, récupération de solde  -> déduit pour « dispo EPI »
 *   Maladie     : maladie                                                     -> déduit pour « dispo EPI »
 *   Formation   : formation standard, congé formation ministère / EPI         -> déduit pour « dispo client »
 *   Supervision : supervision individuelle, supervision de groupe             -> déduit pour « dispo client »
 *   Normal / Deplacement / Autre : comptent dans le total sans déduction.
 */
enum CategorieHeure: string
{
    case Normal = 'normal';
    case Conge = 'conge';
    case Maladie = 'maladie';
    case Formation = 'formation';
    case Supervision = 'supervision';
    case Deplacement = 'deplacement';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Heures normales',
            self::Conge => 'Congés et récupérations',
            self::Maladie => 'Maladie',
            self::Formation => 'Formation',
            self::Supervision => 'Supervision',
            self::Deplacement => 'Déplacements',
            self::Autre => 'Autre',
        };
    }

    /** Colonne du cache `statistiques_mensuelles` alimentée par cette catégorie. */
    public function colonneStatistique(): string
    {
        return 'minutes_'.match ($this) {
            self::Normal => 'normales',
            self::Conge => 'conge',
            self::Maladie => 'maladie',
            self::Formation => 'formation',
            self::Supervision => 'supervision',
            self::Deplacement => 'deplacement',
            self::Autre => 'autres',
        };
    }
}
