<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['equipos', 'jugadores'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('administrador_id')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['equipos', 'jugadores'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('administrador_id');
            });
        }
    }
};
