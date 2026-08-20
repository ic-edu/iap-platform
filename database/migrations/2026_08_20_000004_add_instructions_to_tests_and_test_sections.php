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
        Schema::table('tests', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('pass_score');
        });

        Schema::table('test_sections', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('section_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });

        Schema::table('test_sections', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }
};
