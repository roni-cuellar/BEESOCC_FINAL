<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->enum('tipo_resultado', ['deportivo', 'administrativo'])->default('deportivo');
            // No se infiere una oficialización para los partidos históricos.
            $table->dateTime('oficializado_at')->nullable();
        });
        Schema::create('auditoria_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained('campeonatos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('entidad_tipo', 40);
            $table->unsignedBigInteger('entidad_id');
            $table->string('accion', 40);
            $table->text('motivo');
            $table->json('anterior_json')->nullable();
            $table->json('posterior_json')->nullable();
            $table->dateTime('fecha');
            $table->index(['campeonato_id', 'entidad_tipo', 'entidad_id']);
        });
        Schema::create('autorizaciones_correccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->restrictOnDelete();
            $table->foreignId('solicitado_por_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('autorizado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo');
            $table->enum('estado', ['solicitada', 'aprobada', 'rechazada', 'consumida']);
            $table->enum('alcance', ['eventos', 'resultado']);
            $table->dateTime('fecha_resolucion')->nullable();
            $table->dateTime('fecha_consumo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizaciones_correccion');
        Schema::dropIfExists('auditoria_cambios');
        Schema::table('partidos', fn (Blueprint $table) => $table->dropColumn(['tipo_resultado', 'oficializado_at']));
    }
};
