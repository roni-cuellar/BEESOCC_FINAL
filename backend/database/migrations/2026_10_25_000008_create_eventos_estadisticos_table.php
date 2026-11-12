<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_estadisticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_partido_id')->constrained('equipo_partido')->cascadeOnDelete();
            $table->foreignId('plantilla_id')->constrained('plantillas')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->unsignedSmallInteger('minuto');
            // Permite vincular una asistencia con su gol sin eliminarla si se borra el gol.
            $table->foreignId('gol_relacionado_id')->nullable()->constrained('eventos_estadisticos')->nullOnDelete();
            $table->timestamps();
        });
    }

    // Define cómo revertir esta migración.
    public function down(): void
    {
        Schema::dropIfExists('eventos_estadisticos');
    }
};
