<?php

/*
|--------------------------------------------------------------------------
| Paramètres métier de l'application de suivi d'efficacité (Fondation EPI)
|--------------------------------------------------------------------------
|
| Valeurs issues du fichier « Données app EPI.xlsx » validé avec la Fondation.
| Elles sont surchargeables par l'environnement (.env) sans toucher au code.
|
*/

return [

    // Objectif d'efficacité réelle (prestations facturables / heures dispo client).
    'objectif_efficacite' => (float) env('EPI_OBJECTIF_EFFICACITE', 0.75),

    // Profondeur (en jours) de la période glissante relue à chaque import quotidien.
    // Nécessaire pour détecter les prestations supprimées côté EpiLogg (pas de soft delete).
    'import_fenetre_jours' => (int) env('EPI_IMPORT_FENETRE_JOURS', 60),

];
