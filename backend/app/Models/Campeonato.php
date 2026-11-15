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

    public const FORMATO_ELIMINACION = 'eliminacion_directa';

    public const FORMATO_GRUPOS = 'grupos_eliminacion';

    public function fases(): HasMany
    {
        return $this->hasMany(Fase::class)->orderBy('orden');
    }

    protected function casts(): array
    {
        return ['publicado' => 'boolean'];
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }

    public function asignacionesArbitro(): HasMany
    {
        return $this->hasMany(AsignacionArbitro::class);
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

    public function setFormatoAttribute(string $value): void
    {
        if (! in_array($value, [self::FORMATO_TODOS_CONTRA_TODOS, self::FORMATO_ELIMINACION, self::FORMATO_GRUPOS], true)) {
            throw new InvalidArgumentException('El formato debe ser todos_contra_todos, eliminacion_directa o grupos_eliminacion.');
        }

        $this->attributes['formato'] = $value;
    }
}
