<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

class Grupo extends Model
{
    use HasFactory;

            /** @pest-mutate-ignore */
    public const docentes_minimos = 1;

    /** @pest-mutate-ignore */
    public const docentes_maximos = 2;

    /** @pest-mutate-ignore */
    public const estudiantes_minimos = 15;

    /** @pest-mutate-ignore */
    public const nota_minima = 0;

    /** @pest-mutate-ignore */
    public const nota_maxima = 10;

    protected $table = 'grupos';

    /** @pest-mutate-ignore */
    protected $fillable = ['curso_id', 'numero_ciclo', 'ano', 'numero_grupo', 'abierto'];
    protected function casts(): array
    {
        return [
            'abierto' => 'boolean',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'grupo_docente')->withTimestamps();
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiantes_matriculados')
            ->using(EstudianteMatriculado::class)
            ->withPivot('nota')
            ->withTimestamps();
    }

    public function asignarDocente(Docente|int $profe): void
    {
        if (is_int($profe)) {
            $profe = Docente::where('cod_empleado', $profe)->firstOrFail();
        }

        if ($this->docentes()->where('docentes.id', $profe->id)->exists()) {
            return;
        }

        if ($this->docentes()->count() >= self::docentes_maximos) {
            throw new DomainException('El grupo ya tiene a los dos docentes asignados.');
        }

        $this->docentes()->attach($profe->id);
    }

    public function puedeAbrirse(): bool
    {
        $tieneDocente = $this->docentes()->count() >= self::docentes_minimos;
        $tieneCupo = $this->estudiantes()->count() >= self::estudiantes_minimos;

        return $tieneDocente && $tieneCupo;
    }

    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new DomainException(
                'El grupo necesita por lo menos 1 docente y 15 estudiantes matriculados para poder abrirse.'
            );
        }

        $this->abierto = true;
        $this->save();
    }

    public function registrarNota(Estudiante $estudiante, float $nota): void
    {
        if ($nota < self::nota_minima || $nota > self::nota_maxima) {
            throw new InvalidArgumentException('La nota tiene que estar entre 0 y 10.');
        }

        $filas = EstudianteMatriculado::where('grupo_id', $this->id)
            ->where('estudiante_id', $estudiante->id)
            ->update(['nota' => $nota]);

        if ($filas === 0) {
            throw new DomainException('El estudiante no está matriculado en este grupo.');
        }
    }
}