<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUuid('sku_id')->nullable()->constrained('skus')->nullOnDelete();
            $table->unsignedInteger('price');
            $table->unsignedInteger('pay_price');
            $table->string('status');
            $table->string('tracking_code')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('sku_id');
            $table->index('status');
            $table->index('tracking_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
