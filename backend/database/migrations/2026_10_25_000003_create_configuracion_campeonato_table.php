<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_campeonato', function (Blueprint $table) {
            $table->id();
            // Cada campeonato puede tener como máximo una configuración.
            $table->foreignId('campeonato_id')->unique()->constrained('campeonatos')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_vueltas')->default(1);
            $table->string('orden_desempate', 100)->default('puntos,diferencia_goles,goles_favor');
            $table->date('inicio_inscripciones')->nullable();
            $table->date('fin_inscripciones')->nullable();
            $table->date('inicio_transferencias')->nullable();
            $table->date('fin_transferencias')->nullable();
            $table->timestamps();
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('configuracion_campeonato');
    }
};
