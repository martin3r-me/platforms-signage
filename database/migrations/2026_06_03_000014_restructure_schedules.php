<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitpläne werden wiederverwendbare, benannte Pläne:
 * - signage_schedules = Plan (Name, Team)
 * - signage_schedule_rules = Regeln (Wochentage/Uhrzeit -> Playlist/Musik, Priorität)
 * - signage_screens.schedule_id = der dem Bildschirm zugewiesene Plan
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plan-Name ergänzen.
        Schema::table('signage_schedules', function (Blueprint $table) {
            $table->string('name')->nullable()->after('team_id');
        });

        // Alte, regelspezifische Spalten vom Plan entfernen.
        Schema::table('signage_schedules', function (Blueprint $table) {
            foreach (['screen_id', 'playlist_id', 'music_playlist_id'] as $col) {
                if (Schema::hasColumn('signage_schedules', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
        });
        Schema::table('signage_schedules', function (Blueprint $table) {
            foreach (['days_of_week', 'start_time', 'end_time', 'priority', 'active'] as $col) {
                if (Schema::hasColumn('signage_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Regeln-Tabelle.
        Schema::create('signage_schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('signage_schedules')->cascadeOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained('signage_playlists')->nullOnDelete();
            $table->foreignId('music_playlist_id')->nullable()->constrained('signage_playlists')->nullOnDelete();
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['schedule_id', 'active']);
        });

        // Zuweisung am Bildschirm.
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('default_playlist_id')
                ->constrained('signage_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
        });

        Schema::dropIfExists('signage_schedule_rules');

        Schema::table('signage_schedules', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->foreignId('screen_id')->nullable()->constrained('signage_screens')->cascadeOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained('signage_playlists')->cascadeOnDelete();
            $table->foreignId('music_playlist_id')->nullable()->constrained('signage_playlists')->nullOnDelete();
            $table->json('days_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
        });
    }
};
