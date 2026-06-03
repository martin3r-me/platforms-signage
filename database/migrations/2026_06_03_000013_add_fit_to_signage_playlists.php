<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anzeige-Modus pro Wiedergabeliste:
     * contain = Originalformat (ggf. Balken), cover = Vollbild (beschneidet, nicht verzerrt).
     */
    public function up(): void
    {
        Schema::table('signage_playlists', function (Blueprint $table) {
            $table->string('fit', 20)->default('contain')->after('loop');
        });
    }

    public function down(): void
    {
        Schema::table('signage_playlists', function (Blueprint $table) {
            $table->dropColumn('fit');
        });
    }
};
