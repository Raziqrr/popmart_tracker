<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Pop Mart's spuId
            $table->foreignUuid('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->foreignUuid('collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('price'); // minor units
            $table->char('currency', 3);
            $table->enum('business_type', ['shop', 'draw']);
            $table->string('spec_type')->nullable(); // blind_box, normal...
            $table->json('area_codes');
            $table->timestamp('sale_start_at')->nullable();
            $table->timestamp('sale_end_at')->nullable();
            $table->json('tags');
            $table->unsignedInteger('sales')->nullable();
            $table->unsignedInteger('remain_stock')->nullable(); // only present for un-masked SPUs
            $table->string('warehouse')->nullable(); // local, cross_border
            $table->json('raw_payload'); // full response, escape hatch for fields not modeled yet
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->index('category_id');
            $table->index('business_type');
            $table->index('spec_type');
            $table->index('sale_start_at');
            $table->index('slug');
            $table->fullText('name');

            // JSON tag flags -> generated + indexed columns, MySQL can't index JSON directly
            $table->boolean('is_new')
                ->storedAs("json_contains(`tags`, '\"new\"')")
                ->index();
            $table->boolean('is_sold_out')
                ->storedAs("json_contains(`tags`, '\"OUT_OF_STOCK\"')")
                ->index();
            $table->boolean('is_coming_soon')
                ->storedAs("json_contains(`tags`, '\"COMING_SOON\"')")
                ->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
