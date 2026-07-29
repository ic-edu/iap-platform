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
        if (!Schema::hasTable('user_creation_requests')) {
            Schema::create('user_creation_requests', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->string('requested_role');
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('actioned_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_creation_requests');
    }
};
