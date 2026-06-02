<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('signage_screens')->cascadeOnDelete();
            $table->foreignId('playlist_id')->constrained('signage_playlists')->cascadeOnDelete();
            $table->foreignId('music_playlist_id')->nullable()->constrained('signage_playlists')->nullOnDelete();
            // Wochentage als ISO-Nummern: 1=Mo ... 7=So, z.B. [1,2,3,4,5].
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['screen_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_schedules');
    }
};
