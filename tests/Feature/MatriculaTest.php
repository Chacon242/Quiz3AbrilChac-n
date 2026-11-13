<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\QueryException;

mutates(Grupo::class);


beforeEach(function () {
    $this->curso = Curso::create([
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);

    $this->ana = Docente::create([
        'cod_empleado' => 1001,
        'nombre_empleado' => 'Ana',
        'apellidos_empleado' => 'Mora Vargas',
    ]);

    $this->luis = Docente::create([
        'cod_empleado' => 1002,
        'nombre_empleado' => 'Luis',
        'apellidos_empleado' => 'Rojas Solano',
    ]);

    $this->carla = Docente::create([
        'cod_empleado' => 1003,
        'nombre_empleado' => 'Carla',
        'apellidos_empleado' => 'Jiménez Araya',
    ]);
});

function crearGrupo(Curso $curso, int $numero = 1, bool $abierto = false): Grupo
{
    return Grupo::create([
        'curso_id' => $curso->id,
        'numero_ciclo' => 2,
        'ano' => 2026,
        'numero_grupo' => $numero,
        'abierto' => $abierto,
    ]);
}

function crearMaria(Docente $consejero): Estudiante
{
    return Estudiante::create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
        'consejero_id' => $consejero->id,
    ]);
}

function matriculaDe(Grupo $grupo, Estudiante $estudiante): ?EstudianteMatriculado
{
    return EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();
}

test('CP-01 registra un grupo con toda su información y su curso', function () {
    $creado = crearGrupo($this->curso, 1, true);

    $grupo = Grupo::find($creado->id);

    expect($grupo->numero_ciclo)->toBe(2)
        ->and($grupo->ano)->toBe(2026)
        ->and($grupo->numero_grupo)->toBe(1)
        ->and($grupo->abierto)->toBeTrue()
        ->and($grupo->curso->sigla)->toBe('IF6100')
        ->and($grupo->curso->creditos)->toBe(4);

    $this->assertDatabaseCount('grupos', 1);
});

test('CP-02 un curso tiene varios grupos y se distinguen los abiertos', function () {
    crearGrupo($this->curso, 1, true);
    crearGrupo($this->curso, 2, true);
    crearGrupo($this->curso, 3, false);

    $grupos = $this->curso->grupos;
    $abiertos = $grupos->where('abierto', true);

    expect($grupos)->toHaveCount(3)
        ->and($abiertos)->toHaveCount(2)
        ->and($abiertos->pluck('numero_grupo')->sort()->values()->all())->toBe([1, 2])
        ->and($grupos->pluck('curso_id')->unique()->values()->all())->toBe([$this->curso->id]);
});

test('CP-03 un grupo acepta dos docentes', function () {
    $grupo = crearGrupo($this->curso);

    $grupo->asignarDocente(1001);
    $grupo->asignarDocente(1002);

    $codigos = $grupo->docentes->pluck('cod_empleado')->sort()->values()->all();

    expect($codigos)->toBe([1001, 1002])
        ->and($this->ana->grupos->pluck('id'))->toContain($grupo->id);

    $this->assertDatabaseCount('grupo_docente', 2);
});

test('CP-04 rechaza un tercer docente', function () {
    $grupo = crearGrupo($this->curso);
    $grupo->asignarDocente(1001);
    $grupo->asignarDocente(1002);

    expect(fn () => $grupo->asignarDocente(1003))->toThrow(DomainException::class);

    expect($grupo->docentes()->count())->toBe(2);
    $this->assertDatabaseMissing('grupo_docente', [
        'grupo_id' => $grupo->id,
        'docente_id' => $this->carla->id,
    ]);
});

test('CP-05 un grupo con 14 estudiantes no puede abrirse', function () {
    $grupo = crearGrupo($this->curso);
    $grupo->asignarDocente($this->ana);
    $grupo->estudiantes()->attach(Estudiante::factory()->count(14)->create());

    expect($grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);
    expect($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-06 un grupo con 15 estudiantes y un docente se abre', function () {
    $grupo = crearGrupo($this->curso);
    $grupo->asignarDocente($this->ana);
    $grupo->estudiantes()->attach(Estudiante::factory()->count(15)->create());

    expect($grupo->puedeAbrirse())->toBeTrue();
    expect(fn () => $grupo->abrir())->not->toThrow(DomainException::class);
    expect($grupo->fresh()->abierto)->toBeTrue();
});

test('CP-07 un grupo sin docente no se abre aunque tenga cupo completo', function () {
    $grupo = crearGrupo($this->curso);
    $grupo->estudiantes()->attach(Estudiante::factory()->count(20)->create());

    expect($grupo->docentes()->count())->toBe(0);
    expect($grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);
    expect($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-08 un estudiante queda ligado a su profesor consejero', function () {
    expect($this->ana->estudiantes()->count())->toBe(0);

    $maria = crearMaria($this->ana);

    $aconsejados = $this->ana->estudiantes;

    expect($maria->consejero->cod_empleado)->toBe(1001)
        ->and($aconsejados)->toHaveCount(1)
        ->and($aconsejados->first()->carne)->toBe('C12345')
        ->and($maria->fresh()->consejero_id)->toBe($this->ana->id);
});

test('CP-09 cambiar de consejero reemplaza al anterior', function () {
    $maria = crearMaria($this->ana);

    $maria->consejero()->associate($this->luis);
    $maria->save();

    $ana = $this->ana->fresh();
    $luis = $this->luis->fresh();
    $maria = $maria->fresh();

    expect($maria->consejero->cod_empleado)->toBe(1002)
        ->and($ana->estudiantes)->toHaveCount(0)
        ->and($luis->estudiantes)->toHaveCount(1)
        ->and($maria->consejero_id)->toBe($this->luis->id)
        ->and(Estudiante::where('carne', 'C12345')->count())->toBe(1);
});

test('CP-10 al matricular la nota queda vacía', function () {
    $grupo = crearGrupo($this->curso);
    $maria = crearMaria($this->ana);

    $grupo->estudiantes()->attach($maria->id);

    $pivote = $grupo->estudiantes->first()->pivot;

    expect($pivote)->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($pivote->nota)->toBeNull();

    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $grupo->id,
        'estudiante_id' => $maria->id,
    ]);
});

test('CP-11 registra la nota al finalizar el curso', function () {
    $grupo = crearGrupo($this->curso);
    $maria = crearMaria($this->ana);
    $companero = Estudiante::factory()->create();
    $grupo->estudiantes()->attach([$maria->id, $companero->id]);

    $grupo->registrarNota($maria, 8.5);

    $matricula = matriculaDe($grupo, $maria);

    expect($matricula->nota)->toBe(8.5)
        ->and($matricula->grupo_id)->toBe($grupo->id)
        ->and($matricula->estudiante_id)->toBe($maria->id)
        ->and(matriculaDe($grupo, $companero)->nota)->toBeNull();

    $this->assertDatabaseCount('estudiantes_matriculados', 2);
});

test('CP-12 rechaza notas fuera de rango', function (float $nota) {
    $grupo = crearGrupo($this->curso);
    $maria = crearMaria($this->ana);
    $grupo->estudiantes()->attach($maria->id);

    expect(fn () => $grupo->registrarNota($maria, $nota))
        ->toThrow(InvalidArgumentException::class);

    expect(matriculaDe($grupo, $maria)->nota)->toBeNull();
})->with([-1, 10.5]);

test('CP-13 un estudiante no se matricula dos veces en el mismo grupo', function () {
    $grupo = crearGrupo($this->curso);
    $maria = crearMaria($this->ana);
    $grupo->estudiantes()->attach($maria->id);

    expect(fn () => $grupo->estudiantes()->attach($maria->id))
        ->toThrow(QueryException::class);

    expect(EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $maria->id)
        ->count())->toBe(1);
});