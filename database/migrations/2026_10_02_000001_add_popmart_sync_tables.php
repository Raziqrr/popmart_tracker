<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Server-side Pop Mart sync: polling tiers, change events, the call log and
// the payload field register. See README.md, "Data sync".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // How it's sold. Pre-order is buy-now-ship-later (needs an early pass),
            // separate from a scheduled release (sale_start_at). Detection isn't
            // known yet: everything stays 'regular' until the field is found.
            $table->enum('sale_mode', ['regular', 'preorder'])->default('regular')->after('business_type');
            // Stock polling tier (App\Services\PopMart\Sync\SyncTier) and when it's next due.
            $table->string('sync_tier')->default('normal')->after('last_seen_at');
            $table->timestamp('next_stock_check_at')->nullable()->after('sync_tier');
            // Something changed nearby (e.g. a surprise drop in its collection): poll hot until then.
            $table->timestamp('hot_until')->nullable()->after('next_stock_check_at');

            $table->index('next_stock_check_at');
            $table->index('sale_mode');
        });

        Schema::table('skus', function (Blueprint $table) {
            // stock_snapshots only gets a row when stock changes; this records every check.
            $table->timestamp('last_checked_at')->nullable();
        });

        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('sku_id')->nullable()->constrained('skus')->cascadeOnDelete();
            // App\Services\PopMart\Sync\ProductEventType
            $table->string('type');
            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['product_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        // Every Pop Mart call, to learn its real rate limits. Pruned after 7 days.
        Schema::create('popmart_calls', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('lane'); // background | user
            $table->boolean('house')->default(false);
            $table->unsignedSmallInteger('status')->nullable(); // null: never got a response
            $table->string('error')->nullable(); // PopMartError value
            $table->unsignedInteger('duration_ms');
            $table->timestamp('called_at')->useCurrent();

            $table->index('called_at');
            $table->index(['status', 'called_at']);
        });

        // Every field path ever seen in a Pop Mart response, per endpoint. A new
        // row is a schema change worth a look (e.g. how pre-orders are marked).
        Schema::create('popmart_payload_fields', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('path');
            $table->text('example')->nullable();
            $table->timestamp('first_seen_at')->useCurrent();

            $table->unique(['endpoint', 'path']);
            $table->index('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popmart_payload_fields');
        Schema::dropIfExists('popmart_calls');
        Schema::dropIfExists('product_events');

        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn('last_checked_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['next_stock_check_at']);
            $table->dropIndex(['sale_mode']);
            $table->dropColumn(['sale_mode', 'sync_tier', 'next_stock_check_at', 'hot_until']);
        });
    }
};
