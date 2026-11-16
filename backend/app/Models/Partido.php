<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partido extends Model
{
    public function esOficial(): bool
    {
        return $this->estado === 'finalizado' && $this->oficializado_at
            && ($this->tipo_resultado === 'deportivo' || ($this->tipo_resultado === 'administrativo'
                && $this->administrativo_beneficiado_id && $this->administrativo_sancionado_id
                && $this->administrativo_beneficiado_id !== $this->administrativo_sancionado_id
                && $this->administrativo_por_id && trim($this->motivo_administrativo ?? '') !== ''));
    }

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

    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    public function llave(): BelongsTo
    {
        return $this->belongsTo(Llave::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function desempateGrupo(): BelongsTo
    {
        return $this->belongsTo(GrupoDesempate::class, 'grupo_desempate_id');
    }
}
