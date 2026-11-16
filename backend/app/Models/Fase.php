<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fase extends Model
{
    protected $table = 'fases';

    public $timestamps = false;

    protected $fillable = ['campeonato_id', 'orden', 'nombre', 'tipo', 'estado', 'clasificados_por_grupo', 'fase_origen_id', 'clasificacion_huella'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class)->orderBy('orden');
    }

    public function partidos()
    {
        return $this->hasMany(Partido::class);
    }

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class);
    }

    public function rondas()
    {
        return $this->hasMany(Ronda::class)->orderBy('numero');
    }

    public function equipos()
    {
        return $this->hasMany(FaseEquipo::class);
    }
}
