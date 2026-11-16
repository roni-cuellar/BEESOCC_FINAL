<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\ConfiguracionCampeonato;
use App\Services\IntegridadCampeonatoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfiguracionCampeonatoController extends Controller
{
    public function show(Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('view', $campeonato);
        $configuracion = $campeonato->configuracion()->first();

        return response()->json([
            'message' => $configuracion ? 'Configuración consultada correctamente.' : 'Todavía no hay configuración guardada. Se muestran los valores predeterminados.',
            'guardada' => $configuracion !== null,
            'configuracion' => $configuracion ?? [
                'id' => null,
                'campeonato_id' => $campeonato->id,
                'numero_vueltas' => 1,
                'orden_desempate' => 'puntos,diferencia_goles,goles_favor',
                'inicio_inscripciones' => null,
                'fin_inscripciones' => null,
                'inicio_transferencias' => null,
                'fin_transferencias' => null,
                'created_at' => null,
                'updated_at' => null,
            ],
        ]);
    }

    public function store(Request $request, Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('update', $campeonato);
        $datos = $request->validate([
            'numero_vueltas' => 'required|integer|in:1,2',
            'modalidad' => 'nullable|string|max:50',
            'minimo_jugadores' => 'nullable|integer|min:1|max:99',
            'orden_desempate' => ['required', 'string', Rule::in([
                'puntos,diferencia_goles,goles_favor',
                'puntos,goles_favor,diferencia_goles',
            ])],
            'inicio_inscripciones' => 'nullable|date',
            'fin_inscripciones' => 'nullable|date',
            'inicio_transferencias' => 'nullable|date',
            'fin_transferencias' => 'nullable|date',
            'campeonato_id' => 'prohibited',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'string' => 'El campo :attribute debe ser un texto.',
            'numero_vueltas.in' => 'El número de vueltas debe ser 1 o 2.',
            'orden_desempate.in' => 'El orden debe ser puntos,diferencia_goles,goles_favor o puntos,goles_favor,diferencia_goles.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'prohibited' => 'El campeonato se indica únicamente en la URL.',
        ]);
        unset($datos['campeonato_id']);
        $this->validarFechas($datos);

        try {
            return DB::transaction(function () use ($datos, $campeonato) {
                // Comparte el bloqueo utilizado durante la generación del calendario.
                $campeonato = app(IntegridadCampeonatoService::class)->bloquear($campeonato->id, true);

                if ($campeonato->configuracion()->exists()) {
                    return response()->json(['message' => 'El campeonato ya tiene una configuración guardada.'], 409);
                }

                if ($campeonato->partidos()->exists()) {
                    return response()->json(['message' => 'No se puede crear la configuración porque el campeonato ya tiene partidos registrados.'], 409);
                }

                $configuracion = $campeonato->configuracion()->create($datos);
                $configuracion->refresh();

                return response()->json([
                    'message' => 'Configuración creada correctamente.',
                    'guardada' => true,
                    'configuracion' => $configuracion,
                ], 201);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return response()->json(['message' => 'El campeonato ya tiene una configuración guardada.'], 409);
        }
    }

    public function update(Request $request, Campeonato $campeonato): JsonResponse
    {
        Gate::authorize('update', $campeonato);

        return DB::transaction(function () use ($request, $campeonato) {
            $campeonato = app(IntegridadCampeonatoService::class)->bloquear($campeonato->id, true);
            $configuracion = $campeonato->configuracion()->lockForUpdate()->first();

            if (! $configuracion) {
                return response()->json(['message' => 'El campeonato todavía no tiene una configuración guardada.'], 404);
            }

            $datos = $request->validate([
                'numero_vueltas' => 'sometimes|required|integer|in:1,2',
                'modalidad' => 'sometimes|nullable|string|max:50',
                'minimo_jugadores' => 'sometimes|nullable|integer|min:1|max:99',
                'orden_desempate' => ['sometimes', 'required', 'string', Rule::in([
                    'puntos,diferencia_goles,goles_favor',
                    'puntos,goles_favor,diferencia_goles',
                ])],
                'inicio_inscripciones' => 'sometimes|nullable|date',
                'fin_inscripciones' => 'sometimes|nullable|date',
                'inicio_transferencias' => 'sometimes|nullable|date',
                'fin_transferencias' => 'sometimes|nullable|date',
                'campeonato_id' => 'prohibited',
            ], [
                'required' => 'El campo :attribute no puede estar vacío.',
                'integer' => 'El campo :attribute debe ser un número entero.',
                'string' => 'El campo :attribute debe ser un texto.',
                'numero_vueltas.in' => 'El número de vueltas debe ser 1 o 2.',
                'orden_desempate.in' => 'El orden debe ser puntos,diferencia_goles,goles_favor o puntos,goles_favor,diferencia_goles.',
                'date' => 'El campo :attribute debe ser una fecha válida.',
                'prohibited' => 'No se permite cambiar el campeonato de la configuración.',
            ]);
            unset($datos['campeonato_id']);
            $this->validarFechas($datos, $configuracion);

            if (isset($datos['numero_vueltas']) && (int) $datos['numero_vueltas'] !== $configuracion->numero_vueltas
                && $campeonato->partidos()->exists()) {
                return response()->json(['message' => 'No se puede cambiar el número de vueltas porque el campeonato ya tiene partidos registrados.'], 409);
            }

            $configuracion->update($datos);

            return response()->json([
                'message' => 'Configuración actualizada correctamente.',
                'guardada' => true,
                'configuracion' => $configuracion,
            ]);
        });
    }

    private function validarFechas(array $datos, ?ConfiguracionCampeonato $configuracion = null): void
    {
        // Las fechas enviadas reemplazan las existentes, incluyendo los valores NULL.
        $valores = array_merge($configuracion ? $configuracion->getAttributes() : [], $datos);

        foreach (['inscripciones', 'transferencias'] as $periodo) {
            $inicio = 'inicio_'.$periodo;
            $fin = 'fin_'.$periodo;

            if (isset($valores[$inicio], $valores[$fin])) {
                Validator::make($valores, [
                    $fin => 'after_or_equal:'.$inicio,
                ], [
                    'after_or_equal' => 'La fecha final de '.$periodo.' no puede ser anterior a su fecha inicial.',
                ])->validate();
            }
        }
    }
}
