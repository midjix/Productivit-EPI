<?php

namespace App\Enums;

/**
 * Classification métier des compteurs DSK.
 *
 * DSK n'expose que des « comptes » numérotés (noAccount) : c'est la Fondation
 * qui décide, compte par compte, à quelle catégorie il correspond et s'il
 * entre dans les heures de travail effectives (dénominateur du ratio).
 */
enum CategorieHeure: string
{
    case Normal = 'normal';
    case Formation = 'formation';
    case Conge = 'conge';
    case Maladie = 'maladie';
    case Deplacement = 'deplacement';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Heures normales',
            self::Formation => 'Formation',
            self::Conge => 'Congés',
            self::Maladie => 'Maladie',
            self::Deplacement => 'Déplacements',
            self::Autre => 'Autre',
        };
    }
}
