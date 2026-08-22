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
        Schema::create('audio_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('test_id')->nullable()->index();
            $table->string('question_bank_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('group_type', 30)->default('conversation'); // 'conversation' | 'talk'
            $table->unsignedTinyInteger('part_number')->default(3);     // 3 | 4
            $table->string('media_asset_id', 36)->nullable()->index();
            $table->text('audio_url')->nullable();
            $table->text('audio_script')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'audio_group_id')) {
                $table->string('audio_group_id', 36)->nullable()->after('passage_id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'audio_group_id')) {
                $table->dropColumn('audio_group_id');
            }
        });

        Schema::dropIfExists('audio_groups');
    }
};
