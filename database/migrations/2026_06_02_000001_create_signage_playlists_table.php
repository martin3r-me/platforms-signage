<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_playlists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('kind', ['visual', 'music'])->default('visual');
            $table->boolean('loop')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_playlists');
    }
};
