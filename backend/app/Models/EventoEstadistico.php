<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoEstadistico extends Model
{
    protected $table = 'eventos_estadisticos';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'equipo_partido_id',
        'plantilla_id',
        'tipo',
        'minuto',
        'gol_relacionado_id',
    ];

    public function equipoPartido(): BelongsTo
    {
        return $this->belongsTo(EquipoPartido::class, 'equipo_partido_id');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    // Relación con otro evento de la misma tabla.
    public function golRelacionado(): BelongsTo
    {
        return $this->belongsTo(EventoEstadistico::class, 'gol_relacionado_id');
    }
}
