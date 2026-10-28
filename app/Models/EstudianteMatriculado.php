<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EstudianteMatriculado extends Pivot
{
    protected $table = 'estudiante_matriculado';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'nota' => 'float',
        ];
    }
}
