<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->cascadeOnDelete();
            $table->foreignId('campeonato_equipo_id')->constrained('campeonato_equipos')->restrictOnDelete();
            $table->string('rol', 20);
            $table->timestamps();

            // Evita repetir un rol o un equipo dentro del mismo partido.
            $table->unique(['partido_id', 'rol']);
            $table->unique(['partido_id', 'campeonato_equipo_id']);
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('equipo_partido');
    }
};
