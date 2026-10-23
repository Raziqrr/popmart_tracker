<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// POP NOW auto-lock (docs/tickets/auto-lock.md). Auto-renew was dropped: a
// held box's timer can't be reset (tested 2026-09-29), so there are no
// renew columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_lock_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('popmart_account_id')->constrained('popmart_accounts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('target_kind', ['random', 'specific', 'ranked']);
            // Total boxes for random and ranked; null for specific (sum of pick counts).
            $table->unsignedInteger('count')->nullable();
            $table->unsignedInteger('min_confirmations')->default(1);
            $table->boolean('verified_only')->default(false);
            $table->enum('trigger', ['sale_opens', 'restock', 'chance_reached', 'narrowed']);
            $table->decimal('min_chance', 4, 3)->nullable();
            $table->unsignedInteger('lock_duration_seconds')->default(300);
            $table->boolean('checkout_immediately')->default(true);
            $table->unsignedInteger('locks_made')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->index(['enabled', 'product_id']);
            $table->index(['trigger', 'enabled']);
            $table->index('user_id');
        });

        Schema::create('auto_lock_rule_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_lock_rule_id')->constrained('auto_lock_rules')->cascadeOnDelete();
            $table->foreignUuid('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->unsignedInteger('count')->nullable(); // specific
            $table->unsignedInteger('priority')->nullable(); // ranked: 1 is tried first
            $table->unsignedInteger('locks_made')->default(0);

            $table->unique(['auto_lock_rule_id', 'sku_id']);
        });

        Schema::create('auto_lock_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_lock_rule_id')->nullable()->constrained('auto_lock_rules')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pop_now_box_id')->constrained('pop_now_boxes')->cascadeOnDelete();
            $table->foreignUuid('sku_id')->nullable()->constrained('skus')->nullOnDelete();
            $table->decimal('chance_at_lock', 4, 3)->nullable();
            $table->enum('status', ['pending', 'locked', 'purchased', 'expired', 'released', 'failed'])->default('pending');
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('lock_expires_at')->nullable();
            $table->string('error')->nullable();
            $table->json('raw_response')->nullable();
            // One attempt per (rule, box, trigger event): a retried job never locks twice.
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['status', 'lock_expires_at']);
            $table->index(['user_id', 'attempted_at']);
        });

        Schema::table('user_checkouts', function (Blueprint $table) {
            $table->foreign('auto_lock_rule_id')->references('id')->on('auto_lock_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_checkouts', function (Blueprint $table) {
            $table->dropForeign(['auto_lock_rule_id']);
        });

        Schema::dropIfExists('auto_lock_attempts');
        Schema::dropIfExists('auto_lock_rule_picks');
        Schema::dropIfExists('auto_lock_rules');
    }
};
