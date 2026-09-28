<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assessment_standards')) {
            Schema::create('assessment_standards', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('assessment_family', 40)->index();
                $table->string('standard_code', 60);
                $table->string('version', 30);
                $table->string('title', 255);
                $table->string('provider', 100);
                $table->string('status', 30)->default('draft')->index();
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_until')->nullable();
                $table->string('source_name', 255)->nullable();
                $table->text('source_url')->nullable();
                $table->dateTime('source_checked_at')->nullable();
                $table->json('structure_definition');
                $table->json('blueprint_definition')->nullable();
                $table->json('validation_definition')->nullable();
                $table->json('scoring_definition')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['assessment_family', 'version']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_standards');
    }
};
