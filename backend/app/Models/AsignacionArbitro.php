<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionArbitro extends Model
{
    protected $table = 'asignaciones_arbitro';

    protected $fillable = ['usuario_id', 'autorizado_por', 'fecha_inicio', 'fecha_fin', 'motivo_revocacion'];

    protected $hidden = ['campeonato_activo_id'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime'];
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonato::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }
}
