<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plantilla extends Model
{
    protected $table = 'plantillas';

    protected $hidden = ['dorsal_vigente'];

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'campeonato_equipo_id',
        'jugador_id',
        'dorsal',
        'posicion',
        'fecha_ingreso',
        'fecha_salida',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_salida' => 'date',
        ];
    }

    public function estaVigenteEn(string $fecha): bool
    {
        return $this->fecha_ingreso->toDateString() <= $fecha
            && (! $this->fecha_salida || $this->fecha_salida->toDateString() >= $fecha);
    }

    // La inscripción del jugador pertenece a un equipo de un campeonato.
    public function campeonatoEquipo(): BelongsTo
    {
        return $this->belongsTo(CampeonatoEquipo::class, 'campeonato_equipo_id');
    }

    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoEstadistico::class);
    }
}
