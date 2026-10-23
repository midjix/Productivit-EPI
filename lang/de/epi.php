<?php

return [
    'auth' => [
        'identifiants_incorrects' => 'Ungültige Anmeldedaten.',
        'compte_desactive' => 'Dieses Konto ist deaktiviert.',
        'droits_insuffisants' => 'Unzureichende Rechte für diese Aktion.',
        'role_inconnu' => 'Unbekannte Rolle in der Routendefinition: :role',
    ],

    'policies' => [
        'salarie' => 'Sie haben keinen Zugriff auf die Daten dieses Mitarbeiters.',
        'service' => 'Sie haben keinen Zugriff auf die Statistiken dieser Abteilung.',
    ],

    'admin' => [
        'import_en_file' => 'Import in die Warteschlange gestellt.',
        'recalcul_en_file' => 'Neuberechnung in die Warteschlange gestellt.',
        'rien_a_recalculer' => 'Keine importierten Daten: nichts neu zu berechnen.',
        'propre_role' => 'Sie können Ihre eigene Rolle nicht ändern.',
        'propre_compte' => 'Sie können Ihr eigenes Konto nicht deaktivieren.',
    ],

    'matrice' => [
        'minutes_positives' => 'Der Wert einer Pauschale muss strikt positiv sein.',
        'date_posterieure' => 'Das Anwendungsdatum (:date) muss nach dem Beginn der geltenden Regel (:debut) liegen.',
    ],

    'roles' => [
        'salarie' => 'Mitarbeiter',
        'coordinateur' => 'Koordinator',
        'administrateur' => 'Administrator',
    ],

    'categories_heures' => [
        'normal' => 'Normalstunden',
        'conge' => 'Urlaub und Zeitausgleich',
        'maladie' => 'Krankheit',
        'formation' => 'Weiterbildung',
        'supervision' => 'Supervision',
        'deplacement' => 'Fahrt',
        'autre' => 'Sonstiges (nicht im Total)',
    ],

    'planificateur' => 'Zeitplaner',

    'champs' => [
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwortbestätigung',
        'mot_de_passe_actuel' => 'aktuelles Passwort',
        'device_name' => 'Gerätename',
        'annee' => 'Jahr',
        'mois' => 'Monat',
        'du' => 'Startdatum',
        'au' => 'Enddatum',
        'source' => 'Quelle',
        'statut' => 'Status',
        'categorie' => 'Kategorie',
        'compte_effectif' => 'im Total berücksichtigt',
        'actif' => 'aktiv',
        'tarif_id' => 'Tarif',
        'service_id' => 'Abteilung',
        'minutes' => 'Minuten',
        'date_debut' => 'Anwendungsdatum',
        'commentaire' => 'Kommentar',
        'nom' => 'Name',
        'role' => 'Rolle',
        'q' => 'Suche',
        'non_qualifies' => 'nicht qualifiziert',
    ],
];
