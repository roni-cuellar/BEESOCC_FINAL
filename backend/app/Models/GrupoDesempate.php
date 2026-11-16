<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoDesempate extends Model
{
    protected $table = 'grupo_desempates';

    public $timestamps = false;

    protected $fillable = ['grupo_id', 'clave', 'inscripciones', 'posicion_inicio', 'llave_id'];

    protected function casts(): array
    {
        return ['inscripciones' => 'array'];
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function llave()
    {
        return $this->belongsTo(Llave::class);
    }

    public function partidos()
    {
        return $this->hasMany(Partido::class);
    }
}
