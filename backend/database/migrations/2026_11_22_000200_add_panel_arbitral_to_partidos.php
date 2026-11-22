<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->unsignedSmallInteger('duracion_minutos')->nullable();
            $table->unsignedInteger('reloj_segundos')->default(0);
            $table->dateTime('reloj_desde')->nullable();
            $table->json('titulares')->nullable();
            $table->unsignedInteger('version_panel')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropColumn(['duracion_minutos', 'reloj_segundos', 'reloj_desde', 'titulares', 'version_panel']);
        });
    }
};
