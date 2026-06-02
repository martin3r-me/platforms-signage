<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signage_media_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('signage_media')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('token')->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_media_pages');
    }
};
