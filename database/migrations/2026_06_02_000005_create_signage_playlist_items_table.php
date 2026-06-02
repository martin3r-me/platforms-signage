<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('signage_playlists')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('signage_media')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            // Override-Anzeigedauer für Bilder/Dokument-Seiten (Sekunden). Video/Audio: volle Länge.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->enum('transition', ['none', 'fade'])->default('fade');
            $table->timestamps();

            $table->index(['playlist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_playlist_items');
    }
};
