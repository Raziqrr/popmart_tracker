<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sell rate (docs/tickets/fast-selling-products.md), refreshed by popmart:sell-rates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sell_rate_per_hour', 10, 2)->nullable()->after('remain_stock');
            $table->decimal('hours_to_sell_out', 10, 2)->nullable()->after('sell_rate_per_hour');
            $table->timestamp('sell_rate_computed_at')->nullable()->after('hours_to_sell_out');

            $table->index('sell_rate_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sell_rate_per_hour']);
            $table->dropColumn(['sell_rate_per_hour', 'hours_to_sell_out', 'sell_rate_computed_at']);
        });
    }
};
