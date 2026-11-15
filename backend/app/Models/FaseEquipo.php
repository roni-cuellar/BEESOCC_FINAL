<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaseEquipo extends Model
{
    protected $table = 'fase_equipos';

    public $timestamps = false;

    protected $fillable = ['fase_id', 'campeonato_equipo_id', 'siembra', 'estado'];

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function inscripcion()
    {
        return $this->belongsTo(CampeonatoEquipo::class, 'campeonato_equipo_id');
    }
}
