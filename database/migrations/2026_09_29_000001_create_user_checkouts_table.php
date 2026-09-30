<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A POP NOW checkout: held boxes that passed Pop Mart's draw/box/checkoutValidate and
// are waiting for the user to pay. Checkout does NOT extend the hold (confirmed
// 2026-09-29), so expires_at is when the soonest box hold runs out; a checkout still
// unpaid then is failed. See docs/tickets/auto-lock.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('popmart_account_id')->constrained('popmart_accounts')->cascadeOnDelete();
            $table->foreignId('pop_now_set_id')->constrained('pop_now_sets')->cascadeOnDelete();
            // FK added once auto_lock_rules exists; null for a checkout started by hand.
            $table->unsignedBigInteger('auto_lock_rule_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('failure_reason')->nullable();
            // Pop Mart's lockRemainingSeconds when the checkout was validated.
            $table->unsignedInteger('hold_seconds')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
            $table->index('auto_lock_rule_id');
        });

        Schema::create('user_checkout_boxes', function (Blueprint $table) {
            $table->foreignId('user_checkout_id')->constrained('user_checkouts')->cascadeOnDelete();
            $table->foreignId('pop_now_box_id')->constrained('pop_now_boxes')->cascadeOnDelete();

            $table->primary(['user_checkout_id', 'pop_now_box_id']);
            $table->index('pop_now_box_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_checkout_boxes');
        Schema::dropIfExists('user_checkouts');
    }
};
