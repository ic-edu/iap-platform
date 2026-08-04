<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Academic Content Library (ACL) Foundation.
     */
    public function up(): void
    {
        // 1. Configurable Taxonomy Categories
        Schema::create('acl_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('test_type')->default('general'); // toeic, toefl, ielts, placement, grammar, vocabulary, custom
            $table->string('section_code')->nullable();     // e.g. listening, structure, reading, part_1, writing, speaking
            $table->unsignedInteger('target_questions')->default(100);
            $table->text('description')->nullable();
            $table->string('icon')->default('📚');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Versioning History for Academic Content
        Schema::create('acl_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('resource_type');                 // e.g. QuestionBank, Question
            $table->string('resource_id');
            $table->string('version_number')->default('1.0');
            $table->string('title');
            $table->json('snapshot_data');                  // Full payload snapshot
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('change_reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Governance Audit Trails
        Schema::create('acl_audit_trails', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('resource_type');
            $table->string('resource_id');
            $table->string('action');                       // created, updated, submitted, reviewed, approved, published, revision_requested, archive_requested, archived, restore_requested, restored, version_created, version_rolled_back
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->unsignedBigInteger('archive_requested_by')->nullable();
            $table->unsignedBigInteger('restore_requested_by')->nullable();
            $table->string('version')->default('1.0');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('archive_requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('restore_requested_by')->references('id')->on('users')->onDelete('set null');
        });

        // 4. Add acl_category_id & current_version to question_banks if missing
        Schema::table('question_banks', function (Blueprint $table) {
            if (!Schema::hasColumn('question_banks', 'acl_category_id')) {
                $table->string('acl_category_id')->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('question_banks', 'current_version')) {
                $table->string('current_version')->default('1.0')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_banks', function (Blueprint $table) {
            if (Schema::hasColumn('question_banks', 'acl_category_id')) {
                $table->dropColumn('acl_category_id');
            }
            if (Schema::hasColumn('question_banks', 'current_version')) {
                $table->dropColumn('current_version');
            }
        });

        Schema::dropIfExists('acl_audit_trails');
        Schema::dropIfExists('acl_versions');
        Schema::dropIfExists('acl_categories');
    }
};
