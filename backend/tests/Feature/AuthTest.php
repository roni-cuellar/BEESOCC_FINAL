<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->configurationIsCached() || ! $this->app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Autenticación solo se prueba con SQLite en memoria.');
        }

        // Solo se crea el esquema de usuarios en memoria; MySQL no se toca.
        $migracion = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $migracion->up();
    }

    private function datos(): array
    {
        return ['name' => 'Usuario SENA', 'email' => 'prueba@example.test',
            'password' => 'ClavePrueba123', 'password_confirmation' => 'ClavePrueba123'];
    }

    public function test_registro_crea_usuario_con_hash_y_sesion(): void
    {
        $this->postJson('/api/registro', $this->datos())->assertCreated()
            ->assertJsonPath('usuario.email', 'prueba@example.test')
            ->assertJsonMissingPath('usuario.password')->assertJsonMissingPath('usuario.remember_token');
        $usuario = User::firstOrFail();
        $this->assertTrue(Hash::check('ClavePrueba123', $usuario->password));
        $this->assertAuthenticatedAs($usuario, 'web');
        $this->getJson('/api/usuario')->assertOk()->assertJsonPath('usuario.id', $usuario->id);
    }

    public function test_registro_valida_campos_correo_unico_y_confirmacion(): void
    {
        $this->postJson('/api/registro', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->postJson('/api/registro', array_replace($this->datos(), ['email' => 'invalido', 'password' => 'corta']))
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        User::factory()->create(['email' => 'prueba@example.test']);
        $this->postJson('/api/registro', $this->datos())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/registro', array_replace($this->datos(), ['email' => 'otro@example.test', 'password_confirmation' => 'distinta']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_autentica_y_regenera_la_sesion(): void
    {
        $usuario = User::factory()->create(['email' => 'prueba@example.test', 'password' => 'ClavePrueba123']);
        $this->getJson('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $anterior = session()->getId();
        $this->postJson('/api/login', $this->datos())->assertOk()->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonMissingPath('usuario.password');
        $this->assertNotSame($anterior, session()->getId());
        $this->assertAuthenticatedAs($usuario, 'web');
    }

    public function test_login_rechaza_credenciales_y_valida_campos(): void
    {
        User::factory()->create(['email' => 'prueba@example.test']);
        $this->postJson('/api/login', $this->datos())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_invitado_no_puede_consultar_usuario_ni_cerrar_sesion(): void
    {
        $this->getJson('/api/usuario')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_logout_invalida_sesion_y_regenera_csrf(): void
    {
        User::factory()->create(['email' => 'prueba@example.test', 'password' => 'ClavePrueba123']);
        $this->postJson('/api/login', $this->datos())->assertOk();
        session()->put('dato_anterior', 'valor');
        $anterior = session()->getId();
        $token = session()->token();
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest('web');
        $this->assertNotSame($anterior, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertFalse(session()->has('dato_anterior'));
        app('auth')->forgetGuards();
        $this->getJson('/api/usuario')->assertUnauthorized();
    }

    public function test_csrf_es_obligatorio_fuera_del_modo_de_pruebas(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->postJson('/api/login', $this->datos())->assertStatus(419);
        $this->postJson('/api/registro', $this->datos())->assertStatus(419);
        $this->assertSame(0, DB::table('users')->count());
    }
}
