<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Pop Mart's collectionId
            $table->foreignUuid('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->char('area', 2); // MY, SG, TH...
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['theme_id', 'area']);
            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
