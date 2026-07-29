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
        if (Schema::hasTable('tests') && !Schema::hasColumn('tests', 'status')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->string('status')->default('draft')->after('is_published');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tests') && Schema::hasColumn('tests', 'status')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
