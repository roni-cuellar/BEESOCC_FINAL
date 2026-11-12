<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Crea los jugadores reutilizables entre campeonatos.
    public function up(): void
    {
        Schema::create('jugadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->date('fecha_nacimiento');
            $table->string('foto', 255)->nullable();
            $table->timestamps();
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('jugadores');
    }
};
