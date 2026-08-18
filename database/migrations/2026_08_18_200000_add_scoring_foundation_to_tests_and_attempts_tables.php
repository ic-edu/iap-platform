<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('scoring_method')->default('automatic')->after('pass_score');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->string('evaluation_status')->default('not_required')->after('status');
        });

        // Safe backfill of existing records
        DB::table('tests')->whereNull('scoring_method')->update(['scoring_method' => 'automatic']);
        DB::table('attempts')->whereNull('evaluation_status')->update(['evaluation_status' => 'not_required']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('evaluation_status');
        });

        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('scoring_method');
        });
    }
};
