<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ermöglicht "Apps" (dynamische, clientseitig gerenderte Inhalte wie eine Uhr)
     * als Medien-Typ. Dazu wird kind in einen String umgewandelt (Wert 'app')
     * und app_type + config (JSON für die App-Einstellungen) ergänzt.
     */
    public function up(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->string('kind', 20)->change();
            $table->string('app_type', 40)->nullable()->after('kind');
            $table->json('config')->nullable()->after('app_type');
        });
    }

    public function down(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->dropColumn(['app_type', 'config']);
            $table->enum('kind', ['image', 'video', 'audio', 'document'])->change();
        });
    }
};
