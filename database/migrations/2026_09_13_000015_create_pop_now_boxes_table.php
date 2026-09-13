<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pop_now_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_now_set_id')->constrained('pop_now_sets')->cascadeOnDelete();
            $table->string('box_no');
            $table->unsignedInteger('position')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('locked_by_other')->default(false);
            $table->timestamp('lock_started_at')->nullable();
            $table->unsignedInteger('lock_duration_seconds')->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->unique(['pop_now_set_id', 'box_no']);
            $table->index('box_no');
            $table->index('status');
            $table->index('is_locked');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pop_now_boxes');
    }
};
