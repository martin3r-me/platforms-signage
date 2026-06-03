<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_media_folders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'name']);
        });

        Schema::table('signage_media', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('team_id')
                ->constrained('signage_media_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
        Schema::dropIfExists('signage_media_folders');
    }
};
