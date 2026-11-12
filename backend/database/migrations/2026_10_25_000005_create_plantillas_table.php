<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_equipo_id')->constrained('campeonato_equipos')->cascadeOnDelete();
            $table->foreignId('jugador_id')->constrained('jugadores')->restrictOnDelete();
            $table->unsignedTinyInteger('dorsal');
            $table->string('posicion', 50);
            $table->date('fecha_ingreso');
            // Sin fecha de salida, la inscripción sigue activa.
            $table->date('fecha_salida')->nullable();
            $table->timestamps();
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('plantillas');
    }
};
