<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bildschirm-Gruppen: mehrere Bildschirme bündeln, um Einstellungen gemeinsam
 * zuzuweisen. Reine Organisations-/Zuweisungseinheit (n:m über Pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_screen_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('team_id');
        });

        Schema::create('signage_screen_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('signage_screen_groups')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('signage_screens')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'screen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_screen_group');
        Schema::dropIfExists('signage_screen_groups');
    }
};
