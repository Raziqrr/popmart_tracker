<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skus', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Pop Mart's skuId
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku_code')->nullable();
            $table->string('bar_code')->nullable();
            $table->string('box_type')->nullable(); // normal, secret, ''
            $table->unsignedInteger('price');
            $table->char('currency', 3);
            $table->string('main_image')->nullable();
            $table->timestamps();

            $table->index('bar_code');
            $table->index('sku_code');
            $table->index('box_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};
