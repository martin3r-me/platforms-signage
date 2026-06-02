<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_screens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // team_id ist null, solange der Bildschirm noch nicht gekoppelt wurde.
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->string('name')->nullable();
            // Geheimes Geräte-Token (im localStorage des Players) – autorisiert die API.
            $table->string('device_token', 64)->unique();
            // Kurzer, menschenlesbarer Kopplungs-Code (nur solange pending).
            $table->string('pairing_code', 12)->nullable()->index();
            $table->enum('status', ['pending', 'active'])->default('pending');
            $table->foreignId('default_playlist_id')->nullable()->constrained('signage_playlists')->nullOnDelete();
            $table->foreignId('music_playlist_id')->nullable()->constrained('signage_playlists')->nullOnDelete();
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape');
            // Wird bei jeder relevanten Änderung erhöht -> Player lädt neu.
            $table->unsignedBigInteger('content_version')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_screens');
    }
};
