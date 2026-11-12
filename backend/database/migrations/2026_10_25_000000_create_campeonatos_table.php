<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Crea la tabla de campeonatos.
    public function up(): void
    {
        Schema::create('campeonatos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('temporada', 50);
            $table->string('formato', 30);
            $table->string('estado', 20)->default('planificacion');
            $table->timestamps();
        });
    }

    // Revierte la creación de la tabla si se ejecuta un rollback.
    public function down(): void
    {
        Schema::dropIfExists('campeonatos');
    }
};
