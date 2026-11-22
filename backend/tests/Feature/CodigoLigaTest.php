<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CodigoLiga;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

class CodigoLigaTest extends BackendTestCase
{
    private const MIGRACION = '2026_11_22_000000_add_codigo_liga_to_campeonatos.php';

    private const BUSQUEDA = '/api/publico/campeonatos/por-codigo';

    public function test_creacion_reintenta_colisiones_y_solo_el_servidor_asigna_el_codigo(): void
    {
        $primero = $this->campeonato()['codigo_liga'];
        $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{10}$/', $primero);

        $otroCodigo = $primero === 'ABCDEFGH23' ? 'ZXWVUTSR98' : 'ABCDEFGH23';
        $this->partialMock(CodigoLiga::class, function (MockInterface $mock) use ($primero, $otroCodigo) {
            $mock->shouldReceive('nuevo')->twice()->andReturn($primero, $otroCodigo);
        });
        $segundo = $this->campeonato();
        $this->assertSame($otroCodigo, $segundo['codigo_liga']);
        $ruta = '/api/campeonatos/'.$segundo['id'];
        $this->getJson('/api/campeonatos')->assertJsonFragment(['codigo_liga' => $otroCodigo]);
        $this->patchJson($ruta, ['nombre' => 'Liga renombrada'])
            ->assertOk()->assertJsonPath('campeonato.codigo_liga', $otroCodigo);
        $this->patchJson($ruta, ['codigo_liga' => null])->assertUnprocessable()->assertJsonValidationErrors('codigo_liga');
        $this->postJson('/api/campeonatos', [
            'nombre' => 'Torneo', 'temporada' => '2026', 'formato' => 'todos_contra_todos', 'codigo_liga' => $otroCodigo,
        ])->assertUnprocessable()->assertJsonValidationErrors('codigo_liga');

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('campeonatos')->where('id', $segundo['id'])->update(['codigo_liga' => $primero]);
    }

    public function test_consulta_por_codigo_solo_devuelve_ligas_publicadas_sin_campos_privados(): void
    {
        $liga = $this->campeonato();
        $privada = $this->campeonato('Privada');
        $sinDueno = $this->campeonato('Histórica');
        $ruta = '/api/campeonatos/'.$liga['id'];
        $this->patchJson($ruta.'/publicacion', ['publicado' => true])->assertOk();
        DB::table('campeonatos')->where('id', $sinDueno['id'])->update(['administrador_id' => null, 'publicado' => true]);

        $propietario = auth('web')->user();
        auth('web')->logout();
        app('auth')->forgetGuards();
        $buscar = fn (string $codigo) => self::BUSQUEDA.'?'.http_build_query(['codigo' => $codigo]);
        $publica = $buscar(' '.strtolower($liga['codigo_liga']).' ');
        $detalle = $this->getJson('/api/publico/campeonatos/'.$liga['id'])->assertOk()->json();
        $this->getJson($publica)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson($detalle)->assertJsonMissingPath('administrador_id')->assertJsonMissingPath('codigo_liga');
        $this->getJson(self::BUSQUEDA.'?codigo[]=ABC')->assertUnprocessable();
        $this->getJson($buscar('I0'))->assertUnprocessable();
        $inexistente = CodigoLiga::normalizar('2222222222');
        while (DB::table('campeonatos')->where('codigo_liga', $inexistente)->exists()) {
            $inexistente = app(CodigoLiga::class)->nuevo();
        }
        $sinLiga = $this->getJson($buscar($inexistente))->assertNotFound()->json();
        foreach ([$privada, $sinDueno] as $oculta) {
            $this->getJson($buscar($oculta['codigo_liga']))->assertNotFound()->assertExactJson($sinLiga);
        }
        $this->postJson($publica, [])->assertMethodNotAllowed();
        $this->getJson($ruta)->assertUnauthorized();

        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $this->patchJson($ruta, ['nombre' => 'Sin permiso', 'codigo_liga' => $liga['codigo_liga']])->assertForbidden();

        app('auth')->forgetGuards();
        $this->actingAs($propietario, 'web');
        $this->patchJson($ruta.'/publicacion', ['publicado' => false])->assertOk();
        $this->getJson($publica)->assertNotFound()->assertExactJson($sinLiga);
    }

    public function test_migracion_asigna_codigos_al_historico_y_conserva_sus_relaciones(): void
    {
        $anterior = DB::getDefaultConnection();
        config(['database.connections.codigo_legado' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('codigo_legado');
        try {
            foreach (glob(database_path('migrations/*.php')) as $archivo) {
                if (basename($archivo) < self::MIGRACION) {
                    (require $archivo)->up();
                }
            }
            $datos = ['nombre' => 'Histórico', 'temporada' => '2025', 'formato' => 'todos_contra_todos'];
            $id = DB::table('campeonatos')->insertGetId($datos);
            $otra = DB::table('campeonatos')->insertGetId($datos);
            DB::table('configuracion_campeonato')->insert(['campeonato_id' => $id]);
            $antes = (array) DB::table('campeonatos')->find($id);
            $migracion = require database_path('migrations/'.self::MIGRACION);
            $migracion->up();
            $despues = (array) DB::table('campeonatos')->find($id);

            $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{10}$/', $despues['codigo_liga']);
            $this->assertNotSame($despues['codigo_liga'], DB::table('campeonatos')->find($otra)->codigo_liga);
            $this->assertSame($antes, array_diff_key($despues, ['codigo_liga' => true]));
            $this->assertSame($id, DB::table('configuracion_campeonato')->value('campeonato_id'));
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $migracion->up();
            $this->assertSame($despues['codigo_liga'], DB::table('campeonatos')->find($id)->codigo_liga);
        } finally {
            DB::setDefaultConnection($anterior);
            DB::purge('codigo_legado');
        }
    }
}
