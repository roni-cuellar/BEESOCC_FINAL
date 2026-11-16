<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No completar resoluciones antiguas cuya autoridad o motivo se desconocen.
        if (DB::table('partidos')->where('tipo_resultado', 'administrativo')->exists()) {
            throw new RuntimeException('Existen administrativos previos: revisar antes de migrar.');
        }
        Schema::table('partidos', function (Blueprint $table) {
            $table->foreignId('administrativo_beneficiado_id')->nullable()->constrained('campeonato_equipos')->restrictOnDelete();
            $table->foreignId('administrativo_sancionado_id')->nullable()->constrained('campeonato_equipos')->restrictOnDelete();
            $table->foreignId('administrativo_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_administrativo')->nullable();
        });
        Schema::table('campeonato_equipos', function (Blueprint $table) {
            $table->text('motivo_inactividad')->nullable();
            $table->dateTime('inactivado_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            foreach (['administrativo_beneficiado_id', 'administrativo_sancionado_id', 'administrativo_por_id'] as $columna) {
                $table->dropForeign([$columna]);
            }
            $table->dropColumn(['administrativo_beneficiado_id', 'administrativo_sancionado_id', 'administrativo_por_id', 'motivo_administrativo']);
        });
        Schema::table('campeonato_equipos', fn (Blueprint $t) => $t->dropColumn(['motivo_inactividad', 'inactivado_at']));
    }
};
