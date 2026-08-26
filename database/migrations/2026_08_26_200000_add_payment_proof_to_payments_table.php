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
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'proof_path')) {
                    $table->string('proof_path')->nullable()->after('amount');
                }
                if (!Schema::hasColumn('payments', 'proof_original_name')) {
                    $table->string('proof_original_name')->nullable()->after('proof_path');
                }
                if (!Schema::hasColumn('payments', 'proof_uploaded_at')) {
                    $table->timestamp('proof_uploaded_at')->nullable()->after('proof_original_name');
                }
                if (!Schema::hasColumn('payments', 'proof_notes')) {
                    $table->text('proof_notes')->nullable()->after('proof_uploaded_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $columns = ['proof_path', 'proof_original_name', 'proof_uploaded_at', 'proof_notes'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
