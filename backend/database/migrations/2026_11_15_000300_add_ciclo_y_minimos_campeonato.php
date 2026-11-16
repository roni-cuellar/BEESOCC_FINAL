<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detener antes del DDL si el legado contiene dorsales activos repetidos.
        if (DB::table('plantillas')->whereNull('fecha_salida')->groupBy('campeonato_equipo_id', 'dorsal')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay dorsales activos duplicados: revisar sin alterar históricos.');
        }
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
        });
        Schema::table('configuracion_campeonato', function (Blueprint $table) {
            $table->string('modalidad', 50)->nullable();
            $table->unsignedTinyInteger('minimo_jugadores')->nullable();
        });
        Schema::table('plantillas', function (Blueprint $table) {
            // NULL libera el dorsal histórico; no modifica dorsal ni vigencias anteriores.
            $table->unsignedTinyInteger('dorsal_vigente')->nullable()->virtualAs('CASE WHEN fecha_salida IS NULL THEN dorsal ELSE NULL END');
            $table->unique(['campeonato_equipo_id', 'dorsal_vigente'], 'plantillas_dorsal_vigente_unique');
        });
    }

    public function down(): void
    {
        Schema::table('plantillas', function (Blueprint $table) {
            $table->dropUnique('plantillas_dorsal_vigente_unique');
            $table->dropColumn('dorsal_vigente');
        });
        Schema::table('configuracion_campeonato', fn (Blueprint $table) => $table->dropColumn(['modalidad', 'minimo_jugadores']));
        Schema::table('campeonatos', fn (Blueprint $table) => $table->dropColumn(['iniciado_at', 'finalizado_at']));
    }
};
