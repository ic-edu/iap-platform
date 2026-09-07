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
        Schema::create('coupon_campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('assessment_family'); // toeic, toefl, ielts, general, vocational
            $table->string('scope_mode')->default('all_products_in_family'); // all_products_in_family, selected_products
            $table->string('discount_type')->default('percentage'); // percentage, fixed
            $table->decimal('discount_value', 12, 2);
            $table->string('generation_mode')->default('shared'); // shared, batch
            $table->string('code_prefix', 20);
            $table->integer('code_length')->default(6);
            $table->integer('uses_per_code')->default(1);
            $table->integer('total_codes')->default(1);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_campaign_product', function (Blueprint $table) {
            $table->foreignUlid('campaign_id')->constrained('coupon_campaigns')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['campaign_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_campaign_product');
        Schema::dropIfExists('coupon_campaigns');
    }
};
