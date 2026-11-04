<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_curso_y_guardarlo_en_base_de_datos(): void
    {
        $datos = [
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ];

        $respuesta = $this->postJson('/cursos', $datos);

        $respuesta->assertStatus(201)
            ->assertJsonFragment(['sigla' => 'IF6100']);

        $this->assertDatabaseHas('cursos', $datos);
        $this->assertDatabaseCount('cursos', 1);
    }

    public function test_no_guarda_curso_con_datos_invalidos(): void
    {
        $respuesta = $this->postJson('/cursos', ['sigla' => '']);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['sigla', 'nombre_curso', 'creditos']);

        $this->assertDatabaseCount('cursos', 0);
    }
}
