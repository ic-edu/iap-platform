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
        Schema::create('organization_seat_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_entitlement_id')->constrained('organization_entitlements')->cascadeOnDelete();
            $table->foreignUlid('organization_membership_id')->constrained('organization_memberships')->cascadeOnDelete();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active'); // active, released
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['organization_entitlement_id', 'status']);
            $table->index(['organization_membership_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_seat_allocations');
    }
};
