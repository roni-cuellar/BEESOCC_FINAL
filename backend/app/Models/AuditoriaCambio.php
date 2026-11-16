<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditoriaCambio extends Model
{
    protected $table = 'auditoria_cambios';

    public $timestamps = false;

    protected $fillable = [
        'campeonato_id',
        'usuario_id',
        'entidad_tipo',
        'entidad_id',
        'accion',
        'motivo',
        'anterior_json',
        'posterior_json',
        'fecha',
    ];

    protected function casts(): array
    {
        return ['anterior_json' => 'array', 'posterior_json' => 'array', 'fecha' => 'datetime'];
    }

    protected static function booted(): void
    {
        // La aplicación agrega registros; nunca reescribe ni elimina la bitácora.
        static::updating(fn () => throw new LogicException('La auditoría es inmutable.'));
        static::deleting(fn () => throw new LogicException('La auditoría es inmutable.'));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function campeonato(): BelongsTo
    {
        return $this->belongsTo(Campeonato::class);
    }
}
