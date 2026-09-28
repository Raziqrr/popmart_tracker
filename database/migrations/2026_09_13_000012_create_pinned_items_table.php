<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinned_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pinnable_type');
            $table->uuid('pinnable_id');
            $table->timestamps();

            $table->unique(['user_id', 'pinnable_type', 'pinnable_id'], 'pinned_items_user_pinnable_unique');
            $table->index(['pinnable_type', 'pinnable_id'], 'pinned_items_pinnable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinned_items');
    }
};
