<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popmart_account_id')->constrained('popmart_accounts')->cascadeOnDelete();
            $table->string('order_dispatch_num')->unique();
            $table->string('status');
            $table->char('area_code', 2);
            $table->char('currency', 3);
            $table->json('raw_payload');
            $table->timestamp('placed_at')->useCurrent();

            $table->index(['popmart_account_id', 'placed_at']);
            $table->index('status');
            $table->index('placed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
