<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_delete_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('media_asset_id');
            $table->foreign('media_asset_id')->references('id')->on('media_assets')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_delete_requests');
    }
};
