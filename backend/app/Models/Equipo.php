<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }

    protected $table = 'equipos';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'nombre',
        'ciudad',
        'escudo',
    ];

    // El equipo puede inscribirse en varios campeonatos.
    public function inscripcionesCampeonatos(): HasMany
    {
        return $this->hasMany(CampeonatoEquipo::class);
    }
}
