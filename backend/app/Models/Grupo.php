<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'grupos';

    public $timestamps = false;

    protected $fillable = ['fase_id', 'codigo', 'orden'];

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function equipos()
    {
        return $this->hasMany(FaseEquipo::class)->orderBy('id');
    }

    public function partidos()
    {
        return $this->hasMany(Partido::class)->orderBy('jornada')->orderBy('id');
    }
}
