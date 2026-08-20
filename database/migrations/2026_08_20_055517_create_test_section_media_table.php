<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_section_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('test_section_id')->constrained('test_sections')->cascadeOnDelete();
            $table->foreignUlid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('caption')->nullable();
            $table->integer('order')->default(1);
            $table->timestamps();

            $table->unique(['test_section_id', 'media_asset_id']);
            $table->index(['test_section_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_section_media');
    }
};
