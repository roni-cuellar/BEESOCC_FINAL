<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_arbitro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained('campeonatos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('autorizado_por')->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->text('motivo_revocacion')->nullable();
            $table->timestamps();
            // Las revocadas generan NULL; solo una fila activa puede generar cada campeonato_id.
            $table->unsignedBigInteger('campeonato_activo_id')->nullable()
                ->storedAs('CASE WHEN fecha_fin IS NULL THEN campeonato_id ELSE NULL END');
            $table->unique('campeonato_activo_id', 'asignaciones_arbitro_activa_unique');
            $table->index(['campeonato_id', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_arbitro');
    }
};
