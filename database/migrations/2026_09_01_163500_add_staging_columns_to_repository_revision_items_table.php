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
        Schema::table('repository_revision_items', function (Blueprint $table) {
            $table->json('baseline_data')->nullable()->after('status');
            $table->json('proposed_data')->nullable()->after('baseline_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repository_revision_items', function (Blueprint $table) {
            $table->dropColumn(['baseline_data', 'proposed_data']);
        });
    }
};
