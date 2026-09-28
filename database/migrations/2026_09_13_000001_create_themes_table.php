<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Pop Mart's ipId
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('main_image')->nullable();
            $table->timestamps();

            $table->fullText('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
