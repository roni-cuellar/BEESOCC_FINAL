<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Services\ImagenCatalogoService;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;

class ImagenCatalogoTest extends BackendTestCase
{
    private function imagen(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ficticia.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5WQAAAAASUVORK5CYII='));
    }

    private function catalogos(): array
    {
        return [
            ['/api/equipos', 'equipo', 'escudo', Equipo::class, ['nombre' => 'Equipo ficticio', 'ciudad' => 'Ciudad ficticia']],
            ['/api/jugadores', 'jugador', 'foto', Jugador::class, ['nombre' => 'Jugador', 'apellido' => 'Ficticio', 'fecha_nacimiento' => '2000-01-01']],
        ];
    }

    public function test_crear_reemplazar_conservar_y_quitar_referencia_explicita(): void
    {
        foreach ($this->catalogos() as [$ruta, $entidad, $campo, $modelo, $datos]) {
            $this->partialMock(ImagenCatalogoService::class, function (MockInterface $mock) {
                $mock->shouldReceive('subir')->twice()->andReturn('https://example.test/primera.png', 'https://example.test/segunda.png');
            });
            $creado = $this->post($ruta, $datos + [$campo => $this->imagen()], ['Accept' => 'application/json'])
                ->assertCreated()->assertJsonPath($entidad.'.'.$campo, 'https://example.test/primera.png')->json($entidad);
            $destino = $ruta.'/'.$creado['id'];
            $this->patchJson($destino, ['nombre' => 'Nombre modificado'])->assertOk()
                ->assertJsonPath($entidad.'.'.$campo, 'https://example.test/primera.png');
            $this->patchJson($destino, [$campo => null])->assertOk()->assertJsonPath($entidad.'.'.$campo, 'https://example.test/primera.png');
            $this->post($destino, ['_method' => 'PATCH', $campo => $this->imagen()], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath($entidad.'.'.$campo, 'https://example.test/segunda.png');
            $this->patchJson($destino, ['eliminar_imagen' => true])->assertOk()->assertJsonPath($entidad.'.'.$campo, null);
            $this->assertNull($modelo::findOrFail($creado['id'])->{$campo});
        }
    }

    public function test_rechaza_archivos_invalidos_grandes_y_acciones_contradictorias(): void
    {
        $this->partialMock(ImagenCatalogoService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('subir'));
        foreach ($this->catalogos() as [$ruta, $entidad, $campo, $modelo, $datos]) {
            foreach ([UploadedFile::fake()->createWithContent('ficticio.txt', 'texto'), $this->imagen()->size(5121)] as $archivo) {
                $this->post($ruta, $datos + [$campo => $archivo], ['Accept' => 'application/json'])
                    ->assertUnprocessable()->assertJsonValidationErrors($campo);
            }
            $this->post($ruta, $datos + [$campo => $this->imagen(), 'eliminar_imagen' => '1'], ['Accept' => 'application/json'])->assertUnprocessable();
            $this->postJson($ruta, $datos + [$campo => 'https://example.test/no-es-archivo.png'])->assertUnprocessable();
            $this->assertSame(0, $modelo::count());
        }
    }

    public function test_error_de_proveedor_no_crea_ni_modifica_datos_y_no_filtra_detalles(): void
    {
        foreach ($this->catalogos() as [$ruta, $entidad, $campo, $modelo, $datos]) {
            $this->partialMock(ImagenCatalogoService::class, function (MockInterface $mock) {
                $mock->shouldReceive('subir')->twice()->andThrow(new \RuntimeException('Detalle privado ficticio del proveedor'));
            });
            $this->post($ruta, $datos + [$campo => $this->imagen()], ['Accept' => 'application/json'])
                ->assertStatus(502)->assertDontSee('Detalle privado');
            $this->assertSame(0, $modelo::count());
            $registro = $this->postJson($ruta, $datos)->assertCreated()->json($entidad);
            $modelo::findOrFail($registro['id'])->update([$campo => 'https://example.test/anterior.png']);
            $this->post($ruta.'/'.$registro['id'], ['_method' => 'PATCH', 'nombre' => 'No guardar', $campo => $this->imagen()], ['Accept' => 'application/json'])->assertStatus(502);
            $actual = $modelo::findOrFail($registro['id']);
            $this->assertSame($datos['nombre'], $actual->nombre);
            $this->assertSame('https://example.test/anterior.png', $actual->{$campo});
        }
    }

    public function test_ajeno_no_sube_ni_quita_imagen_del_propietario(): void
    {
        $registros = [];
        foreach ($this->catalogos() as [$ruta, $entidad, $campo, $modelo, $datos]) {
            $registros[] = [$ruta, $this->postJson($ruta, $datos)->assertCreated()->json($entidad)['id'], $campo];
        }
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web');
        $this->partialMock(ImagenCatalogoService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('subir'));
        foreach ($registros as [$ruta, $id, $campo]) {
            $this->post($ruta.'/'.$id, ['_method' => 'PATCH', $campo => $this->imagen()], ['Accept' => 'application/json'])->assertForbidden();
            $this->patchJson($ruta.'/'.$id, ['eliminar_imagen' => true])->assertForbidden();
        }
    }
}
