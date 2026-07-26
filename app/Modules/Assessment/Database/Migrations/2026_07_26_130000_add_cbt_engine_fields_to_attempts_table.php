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
        Schema::table('attempts', function (Blueprint $table) {
            $table->string('current_question_id')->nullable()->after('status');
            $table->json('flagged_questions')->nullable()->after('current_question_id');
            $table->json('review_later_questions')->nullable()->after('flagged_questions');
            $table->integer('violations_count')->default(0)->after('review_later_questions');
            $table->string('seed')->nullable()->after('violations_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['current_question_id', 'flagged_questions', 'review_later_questions', 'violations_count', 'seed']);
        });
    }
};
