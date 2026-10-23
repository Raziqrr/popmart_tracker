<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lucky points (docs/tickets/lucky-points.md) and the audit log of every
// action taken on a user's real Pop Mart account (redeem, lock, checkout).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popmart_account_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popmart_account_id')->unique()->constrained('popmart_accounts')->cascadeOnDelete();
            $table->unsignedInteger('balance');
            $table->unsignedInteger('redeem_minimum')->nullable();
            $table->unsignedInteger('expiring_points')->nullable();
            $table->timestamp('expiring_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::create('popmart_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('popmart_account_id')->constrained('popmart_accounts')->cascadeOnDelete();
            $table->string('action'); // redeem_points, lock_boxes, checkout...
            // The client's Idempotency-Key: a retry or double click replays the first result.
            $table->string('idempotency_key');
            $table->json('request')->nullable();
            $table->string('status')->default('pending'); // pending, succeeded, failed
            $table->string('error')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'action', 'idempotency_key']);
            $table->index(['popmart_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popmart_actions');
        Schema::dropIfExists('popmart_account_points');
    }
};
