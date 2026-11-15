<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Llave extends Model
{
    protected $table = 'llaves';

    public $timestamps = false;

    protected $fillable = ['ronda_id', 'numero', 'estado', 'ganador_fase_equipo_id', 'metodo_resolucion', 'penales_plaza_1', 'penales_plaza_2'];

    public function ronda()
    {
        return $this->belongsTo(Ronda::class);
    }

    public function plazas()
    {
        return $this->hasMany(LlavePlaza::class)->orderBy('posicion');
    }

    public function partidos()
    {
        return $this->hasMany(Partido::class)->orderBy('numero_serie');
    }

    public function ganador()
    {
        return $this->belongsTo(FaseEquipo::class, 'ganador_fase_equipo_id');
    }
}
