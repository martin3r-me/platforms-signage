<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Bildschirm kann mehrere Zeitpläne kombinieren (n:m), die ineinander
 * greifen. Ersetzt die 1:1-Zuweisung signage_screens.schedule_id durch die
 * Pivot-Tabelle signage_schedule_screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_schedule_screen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('signage_schedules')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('signage_screens')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['schedule_id', 'screen_id']);
        });

        // Bestehende 1:1-Zuweisungen in die Pivot-Tabelle übernehmen.
        if (Schema::hasColumn('signage_screens', 'schedule_id')) {
            $assignments = DB::table('signage_screens')
                ->whereNotNull('schedule_id')
                ->get(['id', 'schedule_id']);

            foreach ($assignments as $row) {
                DB::table('signage_schedule_screen')->insert([
                    'schedule_id' => $row->schedule_id,
                    'screen_id'   => $row->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            Schema::table('signage_screens', function (Blueprint $table) {
                $table->dropConstrainedForeignId('schedule_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('signage_screens', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('default_playlist_id')
                ->constrained('signage_schedules')->nullOnDelete();
        });

        // Best-effort: ersten zugewiesenen Plan je Bildschirm zurückschreiben.
        $rows = DB::table('signage_schedule_screen')
            ->orderBy('screen_id')->orderBy('schedule_id')->get();

        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->screen_id])) {
                continue;
            }
            DB::table('signage_screens')->where('id', $r->screen_id)
                ->update(['schedule_id' => $r->schedule_id]);
            $seen[$r->screen_id] = true;
        }

        Schema::dropIfExists('signage_schedule_screen');
    }
};
