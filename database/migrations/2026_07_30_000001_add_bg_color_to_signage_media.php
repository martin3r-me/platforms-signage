<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erkannte Rand-/Hintergrundfarbe eines Mediums (Bild/Dokument), z.B. "#ffffff".
 * Wird beim Upload/Konvertieren aus den Eckpixeln bestimmt und vom Player als
 * Letterbox-Farbe genutzt (statt immer Schwarz). NULL = unbekannt -> Fallback Schwarz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->string('bg_color', 9)->nullable()->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->dropColumn('bg_color');
        });
    }
};
