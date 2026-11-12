<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampeonatoEquipo extends Model
{
    protected $table = 'campeonato_equipos';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'campeonato_id',
        'equipo_id',
        'estado',
    ];

    // La inscripción pertenece a un campeonato.
    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonato::class);
    }

    // La inscripción pertenece a un equipo.
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    // Incluye las inscripciones activas y las históricas.
    public function plantillas(): HasMany
    {
        return $this->hasMany(Plantilla::class);
    }

    // Participaciones del equipo inscrito en los partidos.
    public function participacionesPartidos(): HasMany
    {
        return $this->hasMany(EquipoPartido::class);
    }
}
