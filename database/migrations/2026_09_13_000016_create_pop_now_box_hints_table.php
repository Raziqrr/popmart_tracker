<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pop_now_box_hints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_now_box_id')->constrained('pop_now_boxes')->cascadeOnDelete();
            $table->foreignUuid('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->enum('source', ['verified_api', 'user_reported']);
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('confirmations')->default(1);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->unique(['pop_now_box_id', 'sku_id']);
            $table->index('sku_id');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pop_now_box_hints');
    }
};
