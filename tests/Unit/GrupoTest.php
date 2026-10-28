<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrupoTest extends TestCase
{
      use RefreshDatabase;

    public function test_instanciar_grupo_con_toda_su_informacion(): void
    {
        $curso = Curso::create([
            'sigla' => 'POPPY232',
            'nombre_curso' => 'Análisis y Diseño de Abriles <3',
            'creditos' => 8,
        ]);

        $grupo = Grupo::create([
            'curso_id' => $curso->id,
            'numero_ciclo' => 6,
            'ano' => 2050,
            'numero_grupo' => 7,
            'abierto' => true,
        ]);

        $docente1 = Docente::create([
            'cod_empleado' => 676767,
            'nombre_empleado' => 'Abril',
            'apellidos_empleado' => 'Chacón Araya',
        ]);
        $docente2 = Docente::create([
            'cod_empleado' => 767676,
            'nombre_empleado' => 'Diegogo',
            'apellidos_empleado' => 'Alfaro Alvarado',
        ]);

        $grupo->docentes()->attach([$docente1->id, $docente2->id]);

           
        $listaEstudiantes = [
            ['Abril', 'Chacón Araya', 9.0],
            ['Mia', 'Wilson Solano', 8.5],
            ['Benja', 'Pizarro Chaves', 10.0],
            ['Daniel', 'Gadgens Solís', 7.5],
            ['Elena', 'Alvarado Chacón', 8.0],
            ['Jorge', 'Salazar Navarro', 9.5],
            ['Roberth', 'Ponce Juares', 7.0],
            ['Javier', 'Contreras Brenes', 8.8],
            ['Felix', 'Luna Cruz', 9.2],
            ['Sebastián', 'Murillo Vargas', 6.5],
            ['Fernanda', 'Ulate Porras', 8.3],
            ['Esteban', 'Cabalceta Leiva', 7.8],
            ['Karilyn', 'Cortez Segura', 9.7],
            ['Jeaustin', 'Morales Montero', 8.1],
            ['Ian', 'Navarro Ugalde', 9.4],
        ];

        foreach ($listaEstudiantes as $indice => [$nombre, $apellidos, $nota]) {
            $estudiante = Estudiante::create([
                'carne' => sprintf('C%05d', $indice + 1),
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'consejero_id' => $docente1->id,
            ]);

            $grupo->estudiantes()->attach($estudiante->id, ['nota' => $nota]);
        }

        $grupo->load(['curso', 'docentes', 'estudiantes']);

    
        $this->assertEquals(6, $grupo->numero_ciclo);
        $this->assertEquals(2050, $grupo->ano);
        $this->assertEquals(7, $grupo->numero_grupo);
        $this->assertTrue($grupo->abierto);

        $this->assertSame('POPPY232', $grupo->curso->sigla);
        $this->assertSame('Análisis y Diseño de Abriles <3', $grupo->curso->nombre_curso);

        $this->assertGreaterThanOrEqual(1, $grupo->docentes->count());
        $this->assertLessThanOrEqual(2, $grupo->docentes->count());
        $this->assertCount(2, $grupo->docentes);

        $this->assertGreaterThanOrEqual(15, $grupo->estudiantes->count());

        $primero = $grupo->estudiantes->first();
        $this->assertInstanceOf(EstudianteMatriculado::class, $primero->pivot);
        $this->assertEquals(9.0, $primero->pivot->nota);

        $this->assertSame('Abril', $primero->consejero->nombre_empleado);

        $this->assertDatabaseHas('estudiante_matriculado', [
            'grupo_id' => $grupo->id,
            'estudiante_id' => $primero->id,
        ]);
    }
}
