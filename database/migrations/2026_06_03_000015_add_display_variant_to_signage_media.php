<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Heruntergerechnete Anzeige-Variante für Bilder (schnelleres Laden auf TVs).
     */
    public function up(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->string('display_path')->nullable()->after('path');
            $table->string('display_token')->nullable()->after('display_path')->index();
        });
    }

    public function down(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->dropColumn(['display_path', 'display_token']);
        });
    }
};
