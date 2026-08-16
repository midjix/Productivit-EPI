<?php

namespace App\Enums;

/** Cycle de vie d'une exécution d'import. */
enum StatutImport: string
{
    case EnCours = 'en_cours';
    case Succes = 'succes';
    case Echec = 'echec';
}
