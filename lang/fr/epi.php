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

    // Export Excel : noms de feuilles, en-têtes de colonnes, libellés de la synthèse.
    'export' => [
        'feuilles' => [
            'synthese' => 'Synthèse',
            'mois' => 'Mois',
            'salaries' => 'Salariés',
            'services' => 'Services',
            'types_suivi' => 'Types de suivi',
            'tarifs' => 'Tarifs',
            'types_prestation' => 'Types de prestation',
            'comptes_dsk' => 'Compteurs DSK',
        ],
        'meta' => [
            'titre' => "Suivi d'efficacité — Fondation EPI",
            'perimetre' => 'Périmètre',
            'periode' => 'Période',
            'genere_le' => 'Généré le',
            'par' => 'Par',
            'source' => 'Heures en heures décimales (1,50 = 1 h 30). Sources : pointage DSK (heures), prestations EpiLogg valorisées au barème en vigueur.',
            'filtre_types_suivi' => 'Filtre types de suivi',
            'filtre_types_prestation' => 'Filtre types de prestation',
            'tous' => 'tous',
            'oui' => 'Oui',
            'non' => 'Non',
            'non_calculable' => 'Non calculable',
            'sans_tarif' => 'Sans tarif',
            'sans_service' => 'Sans service',
        ],
        'colonnes' => [
            'indicateur' => 'Indicateur',
            'heures' => 'Heures',
            'formule' => 'Calcul',
            'mois' => 'Mois',
            'total' => 'Total heures dispo',
            'conge' => 'Congés / récup.',
            'maladie' => 'Maladie',
            'dispo_epi' => 'Dispo EPI',
            'formation' => 'Formation',
            'supervision' => 'Supervision',
            'interne' => 'Activité interne',
            'dispo_client' => 'Dispo client',
            'facturables' => 'Prestations facturables',
            'forfaits' => 'Forfaits',
            'efficacite' => 'Efficacité',
            'objectif_atteint' => 'Objectif atteint',
            'brute' => 'Efficacité brute',
            'service' => 'Service',
            'type_suivi' => 'Type de suivi',
            'tarif' => 'Tarif',
            'libelle' => 'Libellé',
            'facturable' => 'Facturable',
            'part' => 'Part du facturable',
            'type_prestation' => 'Type de prestation',
            'no' => 'N°',
            'compte' => 'Compteur DSK',
            'categorie' => 'Catégorie',
            'dans_total' => 'Dans le total',
            'nb_salaries' => 'Salariés',
        ],
        'indicateurs' => [
            'total' => 'Total heures dispo',
            'conge' => 'Heures congé / récup. prises',
            'maladie' => 'Heures maladie',
            'dispo_epi' => 'Heures dispo EPI',
            'formation' => 'Formation',
            'supervision' => 'Supervision',
            'interne' => 'Activité interne',
            'dispo_client' => 'Heures dispo client',
            'facturables' => 'Prestations facturables',
            'forfaits' => 'Forfaits saisis',
            'nb_salaries' => 'Salariés',
            'efficacite' => 'Efficacité',
            'objectif' => 'Objectif',
            'objectif_atteint' => 'Objectif atteint',
            'brute' => 'Efficacité brute',
        ],
        'formules' => [
            'total' => 'Somme des compteurs DSK qui entrent dans le total',
            'conge' => 'Compteurs DSK de catégorie « Congés et récupérations »',
            'maladie' => 'Compteurs DSK de catégorie « Maladie »',
            'dispo_epi' => 'Total − congés − maladie',
            'formation' => 'Compteurs DSK de catégorie « Formation »',
            'supervision' => 'Compteurs DSK de catégorie « Supervision »',
            'interne' => 'Prestations EpiLogg à tarif non facturable, valorisées au barème',
            'dispo_client' => 'Dispo EPI − formation − supervision − activité interne',
            'facturables' => 'Prestations EpiLogg à tarif facturable, valorisées au barème en vigueur à chaque date',
            'forfaits' => 'Forfaits saisis dans EpiLogg, tous tarifs confondus',
            'efficacite' => 'Prestations facturables ÷ dispo client',
            'objectif' => 'Objectif fixé par la Fondation',
            'brute' => '(Prestations facturables + activité interne) ÷ total',
        ],
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
