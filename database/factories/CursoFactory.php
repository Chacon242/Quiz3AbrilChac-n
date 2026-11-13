<?php

namespace Database\Factories;

use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    
  protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'sigla' => 'CF' . fake()->unique()->numberBetween(1000, 9999),
            'nombre_curso' => fake()->sentence(3),
            'creditos' => fake()->numberBetween(1, 4),
        ];
    }
}
