<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlavePlaza extends Model
{
    protected $table = 'llave_plazas';

    public $timestamps = false;

    protected $fillable = ['llave_id', 'posicion', 'fase_equipo_id', 'origen_tipo', 'origen_llave_id'];

    public function llave()
    {
        return $this->belongsTo(Llave::class);
    }

    public function participante()
    {
        return $this->belongsTo(FaseEquipo::class, 'fase_equipo_id');
    }

    public function origen()
    {
        return $this->belongsTo(Llave::class, 'origen_llave_id');
    }

    public function origenGrupo()
    {
        return $this->belongsTo(Grupo::class, 'origen_grupo_id');
    }
}
