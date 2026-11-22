<?php

use App\Services\CodigoLiga;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB confirma cada cambio de esquema. Se puede reanudar sin cambiar códigos asignados.
        if (! Schema::hasColumn('campeonatos', 'codigo_liga')) {
            Schema::table('campeonatos', function (Blueprint $table) {
                $table->string('codigo_liga', CodigoLiga::LONGITUD)->nullable();
            });
        }
        if (! Schema::hasIndex('campeonatos', CodigoLiga::INDICE, 'unique')) {
            Schema::table('campeonatos', function (Blueprint $table) {
                $table->unique('codigo_liga', CodigoLiga::INDICE);
            });
        }

        $codigos = app(CodigoLiga::class);
        DB::table('campeonatos')->select('id')->whereNull('codigo_liga')->eachById(
            fn ($fila) => $codigos->guardar(fn ($codigo) => DB::table('campeonatos')
                ->where('id', $fila->id)->whereNull('codigo_liga')->update(['codigo_liga' => $codigo])),
        );

        Schema::table('campeonatos', function (Blueprint $table) {
            $table->string('codigo_liga', CodigoLiga::LONGITUD)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->dropUnique(CodigoLiga::INDICE);
        });
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->dropColumn('codigo_liga');
        });
    }
};
