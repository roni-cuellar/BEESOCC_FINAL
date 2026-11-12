<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jugador extends Model
{
    protected $table = 'jugadores';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'foto',
    ];

    // Incluye las inscripciones activas y las históricas del jugador.
    public function plantillas(): HasMany
    {
        return $this->hasMany(Plantilla::class);
    }

    // Convierte la fecha de nacimiento en un objeto de fecha.
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }
}
