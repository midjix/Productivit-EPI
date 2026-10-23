<?php

namespace App\Http\Requests\Concerns;

/**
 * Libellés des champs dans les messages de validation, dans la langue de la requête
 * (« Le champ année doit être un entier. » plutôt que « The annee field… »).
 */
trait ChampsTraduits
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        $champs = __('epi.champs');

        return is_array($champs) ? $champs : [];
    }
}
