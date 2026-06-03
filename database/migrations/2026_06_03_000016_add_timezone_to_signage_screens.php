<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zeitzone pro Bildschirm – für die Auswertung der Zeitpläne.
     * Null = App-Standardzeitzone.
     */
    public function up(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('orientation');
        });
    }

    public function down(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
