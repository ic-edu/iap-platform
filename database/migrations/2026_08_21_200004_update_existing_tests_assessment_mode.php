<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. TOEIC Full Simulation Test 01 -> simulator
        DB::table('tests')
            ->where('id', '01kz8tgwdv2nbms5h6yvzkvjfs')
            ->update(['assessment_mode' => 'simulator']);

        // 2. TOEIC Listening & Reading for SMK Perhotelan (live/approved) -> real_test
        DB::table('tests')
            ->where('id', '01m0c8an542gsg387k9r8vb2fs')
            ->update(['assessment_mode' => 'real_test']);

        // 3. TOEIC Listening & Reading for SMK Perhotelan (draft duplicate) -> real_test
        DB::table('tests')
            ->where('id', '01m0frya05avcfq18c2pw9sws9')
            ->update(['assessment_mode' => 'real_test']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tests')
            ->where('id', '01m0c8an542gsg387k9r8vb2fs')
            ->update(['assessment_mode' => 'simulator']);

        DB::table('tests')
            ->where('id', '01m0frya05avcfq18c2pw9sws9')
            ->update(['assessment_mode' => 'simulator']);
    }
};
