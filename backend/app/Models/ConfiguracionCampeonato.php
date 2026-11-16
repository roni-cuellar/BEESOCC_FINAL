<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionCampeonato extends Model
{
    protected $table = 'configuracion_campeonato';

    // Campos permitidos para asignación masiva.
    protected $fillable = [
        'campeonato_id',
        'numero_vueltas',
        'modalidad',
        'minimo_jugadores',
        'orden_desempate',
        'inicio_inscripciones',
        'fin_inscripciones',
        'inicio_transferencias',
        'fin_transferencias',
    ];

    protected function casts(): array
    {
        return [
            'numero_vueltas' => 'integer',
            'minimo_jugadores' => 'integer',
            'inicio_inscripciones' => 'date',
            'fin_inscripciones' => 'date',
            'inicio_transferencias' => 'date',
            'fin_transferencias' => 'date',
        ];
    }

    // La configuración pertenece a un campeonato.
    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonato::class);
    }
}
