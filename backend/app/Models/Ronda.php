<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ronda extends Model
{
    protected $table = 'rondas';

    public $timestamps = false;

    protected $fillable = ['fase_id', 'numero', 'nombre', 'modalidad', 'desempate', 'estado'];

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function llaves()
    {
        return $this->hasMany(Llave::class)->orderBy('numero');
    }
}
