<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Campeonato extends Model
{
    public const FORMATO_TODOS_CONTRA_TODOS = 'todos_contra_todos';

    protected function casts(): array
    {
        return ['publicado' => 'boolean'];
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }

    // Campos que se pueden asignar con create() o fill().
    protected $fillable = [
        'nombre',
        'descripcion',
        'temporada',
        'formato',
        'estado',
    ];

    // El campeonato tiene como máximo una configuración.
    public function configuracion(): HasOne
    {
        return $this->hasOne(ConfiguracionCampeonato::class);
    }

    // El campeonato puede tener varias inscripciones de equipos.
    public function inscripcionesEquipos(): HasMany
    {
        return $this->hasMany(CampeonatoEquipo::class);
    }

    // El campeonato puede tener varios partidos.
    public function partidos(): HasMany
    {
        return $this->hasMany(Partido::class);
    }

    // Por ahora solo se permite el formato todos contra todos.
    public function setFormatoAttribute(string $value): void
    {
        if ($value !== self::FORMATO_TODOS_CONTRA_TODOS) {
            throw new InvalidArgumentException('El formato permitido es todos_contra_todos.');
        }

        $this->attributes['formato'] = $value;
    }
}
