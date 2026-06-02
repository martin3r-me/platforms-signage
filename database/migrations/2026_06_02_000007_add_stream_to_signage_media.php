<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            // file = hochgeladene Datei (Standard), stream = externer Audio-Stream/Embed
            $table->string('source_type')->default('file')->after('kind');
            $table->string('stream_url', 1024)->nullable()->after('source_type');
            // true = als <iframe> einbetten (z.B. TuneIn-Embed), false = direkter Audio-Stream
            $table->boolean('is_embed')->default(false)->after('stream_url');
        });
    }

    public function down(): void
    {
        Schema::table('signage_media', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'stream_url', 'is_embed']);
        });
    }
};
