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
        Schema::create('passage_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('test_id', 36)->nullable()->index();
            $table->string('question_bank_id', 36)->nullable()->index();
            $table->string('title')->nullable();
            $table->unsignedTinyInteger('part_number')->default(6); // 6 | 7
            $table->string('passage_type', 20)->default('single');   // 'single' | 'double' | 'triple'
            $table->json('context_metadata')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('passages', function (Blueprint $table) {
            if (!Schema::hasColumn('passages', 'passage_group_id')) {
                $table->string('passage_group_id', 36)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('passages', 'test_id')) {
                $table->string('test_id', 36)->nullable()->after('question_bank_id')->index();
            }
            if (!Schema::hasColumn('passages', 'order_in_group')) {
                $table->unsignedTinyInteger('order_in_group')->default(1)->after('passage_group_id');
            }
            if (!Schema::hasColumn('passages', 'document_type')) {
                $table->string('document_type', 50)->default('article')->after('order_in_group');
            }
            // Allow question_bank_id to be nullable for test-authored passages if needed
        });

        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'passage_group_id')) {
                $table->string('passage_group_id', 36)->nullable()->after('passage_id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'passage_group_id')) {
                $table->dropColumn('passage_group_id');
            }
        });

        Schema::table('passages', function (Blueprint $table) {
            if (Schema::hasColumn('passages', 'document_type')) {
                $table->dropColumn('document_type');
            }
            if (Schema::hasColumn('passages', 'order_in_group')) {
                $table->dropColumn('order_in_group');
            }
            if (Schema::hasColumn('passages', 'test_id')) {
                $table->dropColumn('test_id');
            }
            if (Schema::hasColumn('passages', 'passage_group_id')) {
                $table->dropColumn('passage_group_id');
            }
        });

        Schema::dropIfExists('passage_groups');
    }
};
