<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipoPartido extends Model
{
    protected $table = 'equipo_partido';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'partido_id',
        'campeonato_equipo_id',
        'rol',
    ];

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    // La participación corresponde a un equipo inscrito en un campeonato.
    public function campeonatoEquipo(): BelongsTo
    {
        return $this->belongsTo(CampeonatoEquipo::class, 'campeonato_equipo_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoEstadistico::class);
    }
}
