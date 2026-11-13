<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'numero_ciclo' => fake()->numberBetween(1, 3),
            'ano' => 2026,
            'numero_grupo' => fake()->unique()->numberBetween(1, 99),
            'abierto' => false,
        ];
    }
}
