<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fase extends Model
{
    protected $table = 'fases';

    public $timestamps = false;

    protected $fillable = ['campeonato_id', 'orden', 'nombre', 'tipo', 'estado'];

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
