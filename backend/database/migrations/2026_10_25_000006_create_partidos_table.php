<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained('campeonatos')->cascadeOnDelete();
            $table->dateTime('fecha_hora')->nullable();
            $table->unsignedInteger('jornada');
            $table->string('lugar', 150)->nullable();
            $table->string('estado', 20)->default('programado');
            $table->timestamps();
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('partidos');
    }
};
