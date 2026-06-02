<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Erlaubt, als Hintergrundmusik auch ein einzelnes Audio/Stream-Medium
     * (statt einer Musik-Playlist) am Bildschirm zu hinterlegen.
     */
    public function up(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->foreignId('music_media_id')->nullable()->after('music_playlist_id')
                ->constrained('signage_media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('music_media_id');
        });
    }
};
