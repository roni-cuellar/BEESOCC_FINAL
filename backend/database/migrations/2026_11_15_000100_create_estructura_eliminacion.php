<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained('campeonatos')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('nombre', 80);
            $table->string('tipo', 20);
            $table->string('estado', 20)->default('planificacion');
            $table->unsignedTinyInteger('clasificados_por_grupo')->nullable();
            $table->unique(['campeonato_id', 'orden']);
        });
        // Solo soporte de las FK del MER; no habilita la fase de grupos.
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fase_id')->constrained('fases')->restrictOnDelete();
            $table->string('codigo', 12);
            $table->unsignedSmallInteger('orden');
            $table->unique(['fase_id', 'codigo']);
        });
        Schema::create('fase_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fase_id')->constrained('fases')->restrictOnDelete();
            $table->foreignId('campeonato_equipo_id')->constrained('campeonato_equipos')->restrictOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->restrictOnDelete();
            $table->unsignedSmallInteger('siembra')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->unique(['fase_id', 'campeonato_equipo_id']);
        });
        Schema::create('rondas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fase_id')->constrained('fases')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('nombre', 60);
            $table->enum('modalidad', ['unico', 'ida_vuelta']);
            $table->enum('desempate', ['penales', 'prorroga_penales']);
            $table->string('estado', 20)->default('planificacion');
            $table->unique(['fase_id', 'numero']);
        });
        Schema::create('llaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ronda_id')->constrained('rondas')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('ganador_fase_equipo_id')->nullable()->constrained('fase_equipos')->restrictOnDelete();
            $table->string('metodo_resolucion', 25)->nullable();
            $table->unsignedTinyInteger('penales_plaza_1')->nullable();
            $table->unsignedTinyInteger('penales_plaza_2')->nullable();
            $table->unique(['ronda_id', 'numero']);
        });
        Schema::create('llave_plazas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llave_id')->constrained('llaves')->restrictOnDelete();
            $table->unsignedTinyInteger('posicion');
            $table->foreignId('fase_equipo_id')->nullable()->constrained('fase_equipos')->restrictOnDelete();
            $table->enum('origen_tipo', ['directo', 'grupo_puesto', 'ganador_llave', 'bye']);
            $table->foreignId('origen_grupo_id')->nullable()->constrained('grupos')->restrictOnDelete();
            $table->unsignedTinyInteger('origen_puesto')->nullable();
            $table->foreignId('origen_llave_id')->nullable()->constrained('llaves')->restrictOnDelete();
            $table->unique(['llave_id', 'posicion']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE llave_plazas ADD CONSTRAINT llave_plazas_posicion_check CHECK (posicion IN (1,2))');
        }
        Schema::table('partidos', function (Blueprint $table) {
            // Nullable: no se asigna fase ni llave a los campeonatos históricos.
            $table->foreignId('fase_id')->nullable()->constrained('fases')->restrictOnDelete();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->restrictOnDelete();
            $table->foreignId('llave_id')->nullable()->constrained('llaves')->restrictOnDelete();
            $table->unsignedTinyInteger('numero_serie')->nullable();
            $table->string('tipo_partido', 20)->default('regular');
            $table->unique(['llave_id', 'numero_serie']);
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropUnique(['llave_id', 'numero_serie']);
            $table->dropConstrainedForeignId('llave_id');
            $table->dropConstrainedForeignId('grupo_id');
            $table->dropConstrainedForeignId('fase_id');
            $table->dropColumn(['numero_serie', 'tipo_partido']);
        });
        foreach (['llave_plazas', 'llaves', 'rondas', 'fase_equipos', 'grupos', 'fases'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
