<?php

return [
    'auth' => [
        'identifiants_incorrects' => 'Incorrect credentials.',
        'compte_desactive' => 'This account is disabled.',
        'droits_insuffisants' => 'Insufficient rights for this action.',
        'role_inconnu' => 'Unknown role in route definition: :role',
    ],

    'policies' => [
        'salarie' => 'You do not have access to this employee\'s data.',
        'service' => 'You do not have access to this department\'s statistics.',
    ],

    'admin' => [
        'import_en_file' => 'Import queued.',
        'recalcul_en_file' => 'Recalculation queued.',
        'rien_a_recalculer' => 'No imported data: nothing to recalculate.',
        'propre_role' => 'You cannot change your own role.',
        'propre_compte' => 'You cannot deactivate your own account.',
    ],

    'matrice' => [
        'minutes_positives' => 'The value of a package must be strictly positive.',
        'date_posterieure' => 'The application date (:date) must be after the start of the rule in force (:debut).',
    ],

    'roles' => [
        'salarie' => 'Employee',
        'coordinateur' => 'Coordinator',
        'administrateur' => 'Administrator',
    ],

    'categories_heures' => [
        'normal' => 'Regular hours',
        'conge' => 'Leave and time off in lieu',
        'maladie' => 'Sick leave',
        'formation' => 'Training',
        'supervision' => 'Supervision',
        'deplacement' => 'Travel',
        'autre' => 'Other (excluded from total)',
    ],

    'planificateur' => 'Scheduler',

    'detail' => [
        'epi' => 'EPI total',
        'interne' => 'Internal activity',
        'tarif' => 'Tariff :code',
        'periode_trop_longue' => 'The period cannot exceed :max days.',
    ],

    'champs' => [
        'email' => 'email address',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'mot_de_passe_actuel' => 'current password',
        'device_name' => 'device name',
        'annee' => 'year',
        'mois' => 'month',
        'du' => 'start date',
        'au' => 'end date',
        'source' => 'source',
        'statut' => 'status',
        'categorie' => 'category',
        'compte_effectif' => 'counted in the total',
        'actif' => 'active',
        'tarif_id' => 'tariff',
        'service_id' => 'department',
        'minutes' => 'minutes',
        'date_debut' => 'application date',
        'commentaire' => 'comment',
        'nom' => 'name',
        'role' => 'role',
        'q' => 'search',
        'non_qualifies' => 'unqualified',
        'perimetre' => 'scope',
        'id' => 'identifier',
        'types_suivi' => 'follow-up types',
        'types_prestation' => 'service types',
    ],
];
