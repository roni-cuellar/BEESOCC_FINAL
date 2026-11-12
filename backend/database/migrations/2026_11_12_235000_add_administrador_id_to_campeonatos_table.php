<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            // Los campeonatos anteriores quedan sin propietario hasta confirmar su asignación.
            $table->foreignId('administrador_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('administrador_id');
        });
    }
};
