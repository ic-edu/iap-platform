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
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'valid_from')) {
                $table->timestamp('valid_from')->nullable()->after('used_count');
            }
            if (!Schema::hasColumn('coupons', 'valid_until')) {
                $table->timestamp('valid_until')->nullable()->after('valid_from');
            }
            if (!Schema::hasColumn('coupons', 'deleted_at')) {
                $table->softDeletes()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('coupons', 'valid_until')) {
                $table->dropColumn('valid_until');
            }
            if (Schema::hasColumn('coupons', 'valid_from')) {
                $table->dropColumn('valid_from');
            }
        });
    }
};
