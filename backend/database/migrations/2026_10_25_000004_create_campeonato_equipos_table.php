<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campeonato_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained('campeonatos')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            // Impide inscribir el mismo equipo dos veces en un campeonato.
            $table->unique(['campeonato_id', 'equipo_id']);
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('campeonato_equipos');
    }
};
