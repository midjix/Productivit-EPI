<?php

namespace App\Enums;

/** Systèmes sources lus (en lecture seule) par l'ETL. */
enum SourceImport: string
{
    case Dsk = 'dsk';
    case Epilogg = 'epilogg';
}
