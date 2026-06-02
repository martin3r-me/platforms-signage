<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->enum('kind', ['image', 'video', 'audio', 'document']);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('token')->index();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // Natürliche Länge bei Video/Audio (Sekunden).
            $table->unsignedInteger('duration_seconds')->nullable();
            // Verarbeitungsstatus für Dokument-Konvertierung (PDF/PPTX -> Seitenbilder).
            $table->enum('processing_status', ['ready', 'pending', 'processing', 'failed'])->default('ready');
            $table->unsignedInteger('page_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_media');
    }
};
