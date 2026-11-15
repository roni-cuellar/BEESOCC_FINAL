<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partido extends Model
{
    protected $table = 'partidos';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'campeonato_id',
        'fecha_hora',
        'jornada',
        'lugar',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'oficializado_at' => 'datetime',
        ];
    }

    // El partido pertenece a un campeonato.
    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonato::class);
    }

    // Equipos que participan en el partido.
    public function participantes(): HasMany
    {
        return $this->hasMany(EquipoPartido::class);
    }

    public function correcciones(): HasMany
    {
        return $this->hasMany(AutorizacionCorreccion::class);
    }
}
