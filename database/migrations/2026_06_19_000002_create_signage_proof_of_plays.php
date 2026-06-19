<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof-of-Play: protokolliert, welches Medium wann auf welchem Bildschirm lief.
 * Wird vom Player gebündelt gemeldet. Bewusst schlank + mit Prune-Command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_proof_of_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('signage_screens')->cascadeOnDelete();
            // media_id ohne FK-Constraint: Medien können (soft-)gelöscht werden,
            // die Historie soll erhalten bleiben.
            $table->unsignedBigInteger('media_id')->nullable();
            $table->timestamp('played_at');
            $table->unsignedInteger('seconds')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'screen_id', 'played_at']);
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_proof_of_plays');
    }
};
