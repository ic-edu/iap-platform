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
        if (Schema::hasTable('question_banks')) {
            Schema::table('question_banks', function (Blueprint $table) {
                if (!Schema::hasColumn('question_banks', 'status')) {
                    $table->string('status')->default('draft')->after('test_type');
                }
                if (!Schema::hasColumn('question_banks', 'is_published')) {
                    $table->boolean('is_published')->default(false)->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('question_banks')) {
            Schema::table('question_banks', function (Blueprint $table) {
                if (Schema::hasColumn('question_banks', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('question_banks', 'is_published')) {
                    $table->dropColumn('is_published');
                }
            });
        }
    }
};
