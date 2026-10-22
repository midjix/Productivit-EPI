<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->lexify('SRV???')),
            'nom' => 'Service '.fake()->unique()->word(),
            'actif' => true,
        ];
    }
}
