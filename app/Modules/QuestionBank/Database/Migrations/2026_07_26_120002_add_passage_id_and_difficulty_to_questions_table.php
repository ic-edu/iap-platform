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
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignUlid('passage_id')->nullable()->after('question_bank_id')->constrained('passages')->nullOnDelete();
            $table->string('difficulty')->default('medium')->after('question_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['passage_id']);
            $table->dropColumn(['passage_id', 'difficulty']);
        });
    }
};
