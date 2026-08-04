<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('type', 20)->default('other'); // audio, image, pdf, passage, other
            $table->string('path');                        // storage path
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->string('status', 20)->default('active'); // active, archived
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
