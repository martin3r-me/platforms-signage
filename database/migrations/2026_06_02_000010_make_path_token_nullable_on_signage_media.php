<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Streams und Apps haben keine Datei -> path/token müssen optional sein.
     */
    public function up(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            $table->string('token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
            $table->string('token')->nullable(false)->change();
        });
    }
};
