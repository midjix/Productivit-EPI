<?php

/*
|--------------------------------------------------------------------------
| Textes applicatifs (français — langue de référence de la Fondation)
|--------------------------------------------------------------------------
| Les fichiers auth/validation/… des trois langues sont fournis par laravel-lang ;
| ce fichier contient uniquement les textes propres à l'application.
*/

return [
    'auth' => [
        'identifiants_incorrects' => 'Identifiants incorrects.',
        'compte_desactive' => 'Ce compte est désactivé.',
        'droits_insuffisants' => 'Droits insuffisants pour cette action.',
        'role_inconnu' => 'Rôle inconnu dans la définition de route : :role',
    ],

    'policies' => [
        'salarie' => "Vous n'avez pas accès aux données de ce salarié.",
        'service' => "Vous n'avez pas accès aux statistiques de ce service.",
    ],

    'admin' => [
        'import_en_file' => "Import mis en file d'attente.",
        'recalcul_en_file' => "Recalcul mis en file d'attente.",
        'rien_a_recalculer' => 'Aucune donnée importée : rien à recalculer.',
        'propre_role' => 'Vous ne pouvez pas modifier votre propre rôle.',
        'propre_compte' => 'Vous ne pouvez pas désactiver votre propre compte.',
    ],

    'matrice' => [
        'minutes_positives' => "La valeur d'un forfait doit être strictement positive.",
        'date_posterieure' => "La date d'application (:date) doit être postérieure au début de la règle en vigueur (:debut).",
    ],

    'roles' => [
        'salarie' => 'Salarié',
        'coordinateur' => 'Coordinateur',
        'administrateur' => 'Administrateur',
    ],

    'categories_heures' => [
        'normal' => 'Heures normales',
        'conge' => 'Congés et récupérations',
        'maladie' => 'Maladie',
        'formation' => 'Formation',
        'supervision' => 'Supervision',
        'deplacement' => 'Déplacements',
        'autre' => 'Autre',
    ],

    'planificateur' => 'Planificateur',

    'detail' => [
        'epi' => 'Total EPI',
        'interne' => 'Activité interne',
        'tarif' => 'Tarif :code',
        'periode_trop_longue' => 'La période ne peut pas dépasser :max jours.',
    ],

    // Libellés des champs dans les messages de validation.
    'champs' => [
        'email' => 'adresse email',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'mot_de_passe_actuel' => 'mot de passe actuel',
        'device_name' => "nom de l'appareil",
        'annee' => 'année',
        'mois' => 'mois',
        'du' => 'date de début',
        'au' => 'date de fin',
        'source' => 'source',
        'statut' => 'statut',
        'categorie' => 'catégorie',
        'compte_effectif' => 'prise en compte dans le total',
        'actif' => 'actif',
        'tarif_id' => 'tarif',
        'service_id' => 'service',
        'minutes' => 'minutes',
        'date_debut' => "date d'application",
        'commentaire' => 'commentaire',
        'nom' => 'nom',
        'role' => 'rôle',
        'q' => 'recherche',
        'non_qualifies' => 'non qualifiés',
        'perimetre' => 'périmètre',
        'id' => 'identifiant',
        'types_suivi' => 'types de suivi',
        'types_prestation' => 'types de prestation',
    ],
];
