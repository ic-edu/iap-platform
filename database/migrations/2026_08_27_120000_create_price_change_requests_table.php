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
        if (!Schema::hasTable('price_change_requests')) {
            Schema::create('price_change_requests', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('product_id', 36)->index();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->decimal('current_price_snapshot', 12, 2);
                $table->decimal('proposed_price', 12, 2);
                $table->text('reason');
                $table->string('status', 30)->default('pending')->index();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_change_requests');
    }
};
