<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class GrupoCasosTest extends TestCase
{
      use RefreshDatabase;
    
         private function crearCurso(): Curso
    {
        return Curso::create([
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);
    }

    private function crearGrupo(Curso $curso, int $numeroGrupo = 1, bool $abierto = false): Grupo
    {
        return Grupo::create([
            'curso_id' => $curso->id,
            'numero_ciclo' => 2,
            'ano' => 2026,
            'numero_grupo' => $numeroGrupo,
            'abierto' => $abierto,
        ]);
    }

    private function docente1001(): Docente
    {
        return Docente::create([
            'cod_empleado' => 1001,
            'nombre_empleado' => 'Ana',
            'apellidos_empleado' => 'Mora Vargas',
        ]);
    }

    private function docente1002(): Docente
    {
        return Docente::create([
            'cod_empleado' => 1002,
            'nombre_empleado' => 'Luis',
            'apellidos_empleado' => 'Rojas Solano',
        ]);
    }

    private function docente1003(): Docente
    {
        return Docente::create([
            'cod_empleado' => 1003,
            'nombre_empleado' => 'Carla',
            'apellidos_empleado' => 'Jiménez Araya',
        ]);
    }

    private function matricularEstudiantes(Grupo $grupo, Docente $consejero, int $cantidad): void
    {
        $estudiantes = Estudiante::factory()
            ->count($cantidad)
            ->create(['consejero_id' => $consejero->id]);

        $grupo->estudiantes()->attach($estudiantes->pluck('id')->all());
    }

   public function test_cp01_registrar_grupo_con_su_informacion_y_su_curso(): void
    {
        $curso = $this->crearCurso();
        $grupoCreado = $this->crearGrupo($curso, 1, true);

        $grupo = Grupo::find($grupoCreado->id);

        $this->assertEquals(2, $grupo->numero_ciclo);
        $this->assertEquals(2026, $grupo->ano);
        $this->assertEquals(1, $grupo->numero_grupo);
        $this->assertTrue($grupo->abierto);
        $this->assertIsBool($grupo->abierto);

        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertEquals(4, $grupo->curso->creditos);

        $this->assertDatabaseCount('grupos', 1);
    }

    public function test_cp02_un_curso_tiene_varios_grupos_y_se_distinguen_los_abiertos(): void
    {
        $curso = $this->crearCurso();

        $this->crearGrupo($curso, 1, true);
        $this->crearGrupo($curso, 2, true);
        $this->crearGrupo($curso, 3, false);

        $this->assertCount(3, $curso->grupos);

        $abiertos = $curso->grupos->where('abierto', true);
        $this->assertCount(2, $abiertos);
        $this->assertEqualsCanonicalizing([1, 2], $abiertos->pluck('numero_grupo')->all());

        $this->assertEquals(3, Grupo::where('curso_id', $curso->id)->count());
    }

    public function test_cp03_un_grupo_acepta_dos_docentes(): void
    {
        $curso = $this->crearCurso();
        $grupo = $this->crearGrupo($curso);
        $docente1001 = $this->docente1001();
        $this->docente1002();

        $this->assertEquals(0, $grupo->docentes()->count());

        $grupo->asignarDocente(1001);
        $grupo->asignarDocente(1002);

        $docentes = $grupo->docentes;
        $this->assertCount(2, $docentes);
        $this->assertEqualsCanonicalizing([1001, 1002], $docentes->pluck('cod_empleado')->all());

        $this->assertTrue($docente1001->grupos->contains($grupo));
        $this->assertDatabaseCount('grupo_docente', 2);
    }

    public function test_cp04_un_grupo_rechaza_un_tercer_docente(): void
    {
        $curso = $this->crearCurso();
        $grupo = $this->crearGrupo($curso);
        $this->docente1001();
        $this->docente1002();
        $docente1003 = $this->docente1003();

        $grupo->asignarDocente(1001);
        $grupo->asignarDocente(1002);

        $lanzada = false;
        try {
            $grupo->asignarDocente(1003);
        } catch (DomainException) {
            $lanzada = true;
        }

        $this->assertTrue($lanzada, 'Debió lanzar DomainException al asignar el tercer docente');
        $this->assertEquals(2, $grupo->docentes()->count());
        $this->assertDatabaseMissing('grupo_docente', [
            'grupo_id' => $grupo->id,
            'docente_id' => $docente1003->id,
        ]);
    }

    public function test_cp05_un_grupo_con_14_estudiantes_no_puede_abrirse(): void
    {
        $curso = $this->crearCurso();
        $grupo = $this->crearGrupo($curso, 1, false);
        $docente1001 = $this->docente1001();

        $grupo->asignarDocente(1001);
        $this->matricularEstudiantes($grupo, $docente1001, 14);

        $this->assertFalse($grupo->puedeAbrirse());

        $lanzada = false;
        try {
            $grupo->abrir();
        } catch (DomainException) {
            $lanzada = true;
        }

        $this->assertTrue($lanzada, 'Debió lanzar DomainException al abrir con 14 estudiantes');
        $this->assertFalse($grupo->fresh()->abierto);
    }

    public function test_cp06_un_grupo_con_15_estudiantes_y_un_docente_se_abre(): void
    {
        $curso = $this->crearCurso();
        $grupo = $this->crearGrupo($curso, 1, false);
        $docente1001 = $this->docente1001();

        $grupo->asignarDocente(1001);
        $this->matricularEstudiantes($grupo, $docente1001, 15);

        $this->assertTrue($grupo->puedeAbrirse());

        $grupo->abrir();

        $this->assertTrue($grupo->fresh()->abierto);
    }
}