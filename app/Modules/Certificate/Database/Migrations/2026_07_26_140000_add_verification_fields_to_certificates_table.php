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
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('verification_code')->nullable()->unique()->after('certificate_number');
            $table->string('status')->default('valid')->after('verification_code');
            $table->string('template')->default('internal')->after('status');
            $table->timestamp('revoked_at')->nullable()->after('issue_date');
            $table->timestamp('expires_at')->nullable()->after('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['verification_code', 'status', 'template', 'revoked_at', 'expires_at']);
        });
    }
};
