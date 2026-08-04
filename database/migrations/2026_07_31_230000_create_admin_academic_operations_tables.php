<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Admin Academic Operations Foundation.
     */
    public function up(): void
    {
        // 1. Student Applications Table
        Schema::create('student_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('registration_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('student_name');
            $table->string('selected_program')->default('TOEFL'); // TOEFL, TOEIC, IELTS, General
            $table->string('english_level')->default('Intermediate'); // Beginner, Elementary, Intermediate, Upper-Intermediate, Advanced
            $table->string('placement_test_status')->default('placement_required'); // placement_required, completed, waived
            $table->integer('target_score')->default(550);
            $table->string('application_status')->default('waiting_review'); // waiting_review, placement_required, ready_for_assignment, assigned, completed, rejected
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 2. Teacher Assignments Table
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('course_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('role')->default('lead'); // lead, assistant, mentor
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('status')->default('assigned'); // assigned, active, completed
            $table->text('assignment_notes')->nullable();
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Extend courses table with operational fields if missing
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'program')) {
                $table->string('program')->default('TOEFL')->after('code');
            }
            if (!Schema::hasColumn('courses', 'capacity')) {
                $table->integer('capacity')->default(30)->after('description');
            }
            if (!Schema::hasColumn('courses', 'start_date')) {
                $table->date('start_date')->nullable()->after('capacity');
            }
            if (!Schema::hasColumn('courses', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('courses', 'schedule')) {
                $table->string('schedule')->default('Mon, Wed, Fri 09:00 - 11:00 AM')->after('end_date');
            }
            if (!Schema::hasColumn('courses', 'course_status')) {
                $table->string('course_status')->default('draft')->after('is_active'); // draft, waiting_approval, approved, active, completed, archived
            }
            if (!Schema::hasColumn('courses', 'assigned_teacher_id')) {
                $table->unsignedBigInteger('assigned_teacher_id')->nullable()->after('course_status');
                $table->foreign('assigned_teacher_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'assigned_teacher_id')) {
                $table->dropForeign(['assigned_teacher_id']);
                $table->dropColumn('assigned_teacher_id');
            }
            if (Schema::hasColumn('courses', 'course_status')) {
                $table->dropColumn('course_status');
            }
            if (Schema::hasColumn('courses', 'schedule')) {
                $table->dropColumn('schedule');
            }
            if (Schema::hasColumn('courses', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('courses', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('courses', 'capacity')) {
                $table->dropColumn('capacity');
            }
            if (Schema::hasColumn('courses', 'program')) {
                $table->dropColumn('program');
            }
        });

        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('student_applications');
    }
};
