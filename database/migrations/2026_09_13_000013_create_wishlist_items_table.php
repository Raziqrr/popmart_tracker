<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('watchable_type');
            $table->uuid('watchable_id');
            $table->enum('last_known_status', ['in_stock', 'out_of_stock'])->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'watchable_type', 'watchable_id'], 'wishlist_items_user_watchable_unique');
            $table->index(['watchable_type', 'watchable_id'], 'wishlist_items_watchable_index');
            $table->index('last_known_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
