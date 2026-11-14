<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public function asignacionesArbitro(): HasMany
    {
        return $this->hasMany(AsignacionArbitro::class, 'usuario_id');
    }

    public function autorizacionesArbitro(): HasMany
    {
        return $this->hasMany(AsignacionArbitro::class, 'autorizado_por');
    }

    public function esArbitroDe(Campeonato $campeonato): bool
    {
        return $campeonato->administrador_id !== null && (int) $campeonato->administrador_id !== (int) $this->id
            && $this->asignacionesArbitro()->where('campeonato_id', $campeonato->id)
                ->whereNull('fecha_fin')->where('fecha_inicio', '<=', now())->exists();
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'administrador_id');
    }

    public function jugadores(): HasMany
    {
        return $this->hasMany(Jugador::class, 'administrador_id');
    }

    public function campeonatos(): HasMany
    {
        return $this->hasMany(Campeonato::class, 'administrador_id');
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
