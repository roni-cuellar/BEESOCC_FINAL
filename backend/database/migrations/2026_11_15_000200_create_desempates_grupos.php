<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fases', function (Blueprint $table) {
            $table->foreignId('fase_origen_id')->nullable()->unique()->constrained('fases')->restrictOnDelete();
            $table->string('clasificacion_huella', 64)->nullable();
        });
        Schema::create('grupo_desempates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
            $table->string('clave', 64);
            $table->json('inscripciones');
            $table->unsignedSmallInteger('posicion_inicio');
            $table->foreignId('llave_id')->nullable()->unique()->constrained('llaves')->restrictOnDelete();
            $table->unique(['grupo_id', 'clave']);
        });
        Schema::table('partidos', function (Blueprint $table) {
            $table->foreignId('grupo_desempate_id')->nullable()->constrained('grupo_desempates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partidos', fn (Blueprint $table) => $table->dropConstrainedForeignId('grupo_desempate_id'));
        Schema::dropIfExists('grupo_desempates');
        Schema::table('fases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fase_origen_id');
            $table->dropColumn('clasificacion_huella');
        });
    }
};
