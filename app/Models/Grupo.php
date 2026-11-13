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

    protected $table = 'grupos';

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

        if ($this->docentes()->count() >= $this->docentesMaximos()) {
            throw new DomainException('El grupo ya tiene a los dos docentes asignados.');
        }

        $this->docentes()->attach($profe->id);
    }

    public function puedeAbrirse(): bool
    {
        $tieneDocente = $this->docentes()->count() >= $this->docentesMinimos();
        $tieneCupo = $this->estudiantes()->count() >= $this->estudiantesMinimos();

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
        if ($nota < $this->notaMinima() || $nota > $this->notaMaxima()) {
            throw new InvalidArgumentException('La nota tiene que estar entre 0 y 10.');
        }

        $filas = EstudianteMatriculado::where('grupo_id', $this->id)
            ->where('estudiante_id', $estudiante->id)
            ->update(['nota' => $nota]);

        if ($filas === 0) {
            throw new DomainException('El estudiante no está matriculado en este grupo.');
        }
    }

    private function docentesMinimos(): int
    {
        return 1;
    }

    private function docentesMaximos(): int
    {
        return 2;
    }

    private function estudiantesMinimos(): int
    {
        return 15;
    }

    private function notaMinima(): float
    {
        return 0;
    }

    private function notaMaxima(): float
    {
        return 10;
    }
}