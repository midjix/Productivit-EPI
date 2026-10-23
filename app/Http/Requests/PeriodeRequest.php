<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de période des endpoints de consultation (`?annee=2026&mois=4`).
 *
 * Validation serveur systématique : une valeur non entière ou hors bornes
 * est rejetée (422) avant d'atteindre la moindre requête SQL. Les valeurs
 * validées sont ensuite passées à Eloquent en paramètres liés (requêtes préparées).
 */
class PeriodeRequest extends FormRequest
{
    use ChampsTraduits;

    /** L'autorisation (qui voit quoi) est portée par les policies, pas par la requête. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'annee' => ['sometimes', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'mois' => ['sometimes', 'nullable', 'integer', 'between:1,12'],
        ];
    }

    /** Année demandée, année courante par défaut. */
    public function annee(): int
    {
        return (int) ($this->validated('annee') ?? now()->year);
    }

    /** Mois demandé, ou null pour « toute l'année ». */
    public function mois(): ?int
    {
        $mois = $this->validated('mois');

        return $mois === null ? null : (int) $mois;
    }
}
