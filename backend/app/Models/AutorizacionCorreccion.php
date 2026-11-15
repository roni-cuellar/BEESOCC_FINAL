<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutorizacionCorreccion extends Model
{
    protected $table = 'autorizaciones_correccion';

    public $timestamps = false;

    // Los responsables, estados y fechas solo los asigna el servicio.
    protected $fillable = ['motivo', 'alcance'];

    protected function casts(): array
    {
        return ['fecha_resolucion' => 'datetime', 'fecha_consumo' => 'datetime'];
    }

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_id');
    }
}
