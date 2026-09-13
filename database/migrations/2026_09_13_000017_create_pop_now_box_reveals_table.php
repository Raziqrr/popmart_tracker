<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pop_now_box_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_now_box_id')->constrained('pop_now_boxes')->cascadeOnDelete();
            $table->foreignUuid('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->foreignId('revealed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('source', ['order_reveal', 'open_box_api', 'user_reported']);
            $table->timestamp('revealed_at')->useCurrent();

            $table->index('pop_now_box_id');
            $table->index('sku_id');
            $table->index('revealed_at');
            $table->index(['sku_id', 'revealed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pop_now_box_reveals');
    }
};
