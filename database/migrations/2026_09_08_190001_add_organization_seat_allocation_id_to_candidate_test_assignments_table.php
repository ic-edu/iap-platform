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
        Schema::table('candidate_test_assignments', function (Blueprint $table) {
            $table->string('organization_seat_allocation_id')->nullable()->after('order_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_test_assignments', function (Blueprint $table) {
            $table->dropIndex(['organization_seat_allocation_id']);
            $table->dropColumn('organization_seat_allocation_id');
        });
    }
};
