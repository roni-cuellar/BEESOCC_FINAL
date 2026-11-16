<?php

namespace Tests\Feature;

use App\Models\EventoEstadistico;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

abstract class BackendTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $base = $this->prepararBasePruebas();
        $base->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($base) {
            while ($base->transactionLevel() > 0) {
                $base->rollBack();
            }
        });
        $this->actingAs(User::factory()->create(), 'web');
    }

    protected function prepararBasePruebas(): Connection
    {
        // Se comprueba el destino antes de migrar o escribir cualquier dato.
        if (! $this->app->environment('testing') || $this->app->configurationIsCached()) {
            throw new RuntimeException('Las pruebas requieren APP_ENV=testing y configuración sin caché.');
        }

        $conexion = config('database.default');
        $configuracion = config('database.connections.'.$conexion);

        if (! empty($configuracion['url'])) {
            throw new RuntimeException('Las pruebas no permiten DB_URL: declara explícitamente la base de pruebas.');
        }

        $sqliteSeguro = $conexion === 'sqlite' && $configuracion['database'] === ':memory:';
        if (! $sqliteSeguro) {
            throw new RuntimeException('Este Sprint solo permite SQLite :memory: para las pruebas.');
        }

        $base = DB::connection();

        if ($sqliteSeguro && (int) $base->selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
            throw new RuntimeException('SQLite debe tener las claves foráneas activadas.');
        }

        // Esquema efímero en memoria, sin ejecutar comandos migrate sobre MySQL.
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            (require $archivo)->up();
        }

        return $base;
    }

    protected function campeonato(string $nombre = 'Copa de prueba'): array
    {
        return $this->postJson('/api/campeonatos', [
            'nombre' => $nombre, 'temporada' => '2026', 'formato' => 'todos_contra_todos',
        ])->assertCreated()->json('campeonato');
    }

    protected function equipo(string $nombre = 'Equipo de prueba'): array
    {
        return $this->postJson('/api/equipos', ['nombre' => $nombre, 'ciudad' => 'Bogotá', 'escudo' => null])
            ->assertCreated()->json('equipo');
    }

    protected function inscripcion(int $campeonatoId, string $nombre = 'Equipo de prueba'): array
    {
        $equipo = $this->equipo($nombre);

        return $this->postJson('/api/campeonatos/'.$campeonatoId.'/inscripciones-equipos', ['equipo_id' => $equipo['id']])
            ->assertCreated()->json('inscripcion');
    }

    protected function jugador(string $nombre = 'Carlos'): array
    {
        return $this->postJson('/api/jugadores', [
            'nombre' => $nombre, 'apellido' => 'Pérez', 'fecha_nacimiento' => '2000-05-15', 'foto' => null,
        ])->assertCreated()->json('jugador');
    }

    protected function plantilla(int $inscripcionId, ?int $jugadorId = null, int $dorsal = 10): array
    {
        $jugadorId = $jugadorId ?? $this->jugador()['id'];

        return $this->postJson('/api/inscripciones-equipos/'.$inscripcionId.'/plantillas', [
            'jugador_id' => $jugadorId, 'dorsal' => $dorsal, 'posicion' => 'Delantero', 'fecha_ingreso' => '2026-01-01',
        ])->assertCreated()->json('plantilla');
    }

    protected function partido(int $campeonatoId, int $localId, int $visitanteId, int $jornada = 1): array
    {
        return $this->postJson('/api/campeonatos/'.$campeonatoId.'/partidos', [
            'jornada' => $jornada, 'local_id' => $localId, 'visitante_id' => $visitanteId,
        ])->assertCreated()->json('partido');
    }

    protected function participante(array $partido, string $rol): int
    {
        $participante = collect($partido['participantes'])->firstWhere('rol', $rol);
        $this->assertNotNull($participante);

        return $participante['id'];
    }

    protected function evento(array $partido, int $participanteId, int $plantillaId, string $tipo = 'gol', ?int $golId = null): array
    {
        return $this->solicitudArbitral('post', '/api/partidos/'.$partido['id'].'/eventos', [
            'equipo_partido_id' => $participanteId, 'plantilla_id' => $plantillaId,
            'tipo' => $tipo, 'minuto' => 15, 'gol_relacionado_id' => $golId,
        ])->assertCreated()->json('evento');
    }

    protected function escenario(bool $iniciar = true): array
    {
        $campeonato = $this->campeonato();
        $local = $this->inscripcion($campeonato['id'], 'Águilas');
        $visitante = $this->inscripcion($campeonato['id'], 'Leones');
        $plantillaLocal = $this->plantilla($local['id']);
        $plantillaVisitante = $this->plantilla($visitante['id']);
        $partido = $this->partido($campeonato['id'], $local['id'], $visitante['id']);

        if ($iniciar) {
            $this->solicitudArbitral('patch', '/api/partidos/'.$partido['id'], ['estado' => 'en_curso'])->assertOk();
        }

        return compact('campeonato', 'local', 'visitante', 'plantillaLocal', 'plantillaVisitante', 'partido');
    }

    protected function solicitudArbitral(string $metodo, string $ruta, array $datos = []): TestResponse
    {
        // Los casos deportivos anteriores ahora separan al organizador del árbitro.
        preg_match('#/api/(partidos|eventos)/(\d+)#', $ruta, $coincidencia);
        $partido = $coincidencia[1] === 'partidos'
            ? Partido::findOrFail($coincidencia[2])
            : EventoEstadistico::findOrFail($coincidencia[2])->equipoPartido->partido;
        $administrador = auth('web')->user();
        $asignacion = $partido->campeonato->asignacionesArbitro()->whereNull('fecha_fin')->first();
        if (! $asignacion) {
            $arbitro = User::factory()->create();
            $this->postJson('/api/campeonatos/'.$partido->campeonato_id.'/arbitro', ['usuario_id' => $arbitro->id])->assertCreated();
        } else {
            $arbitro = $asignacion->usuario;
        }

        try {
            app('auth')->forgetGuards();
            $this->actingAs($arbitro, 'web');

            return $this->{$metodo.'Json'}($ruta, $datos);
        } finally {
            app('auth')->forgetGuards();
            $this->actingAs($administrador, 'web');
        }
    }
}
