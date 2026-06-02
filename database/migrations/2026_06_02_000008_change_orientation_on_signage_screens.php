<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erweitert die Ausrichtung von 2 auf 4 Werte:
     * landscape, landscape_180, portrait, portrait_180.
     * Dazu wird die enum-Spalte in eine string-Spalte umgewandelt.
     */
    public function up(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->string('orientation', 32)->default('landscape')->change();
        });
    }

    public function down(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape')->change();
        });
    }
};
