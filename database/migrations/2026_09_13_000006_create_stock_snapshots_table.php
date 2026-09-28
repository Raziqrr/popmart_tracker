<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->unsignedInteger('stock');
            $table->boolean('has_stock');
            $table->timestamp('checked_at')->useCurrent();

            $table->index(['sku_id', 'checked_at']);
            $table->index('checked_at');
            $table->index('has_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
    }
};
