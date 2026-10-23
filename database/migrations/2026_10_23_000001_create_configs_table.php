<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value');
            $table->string('type')->default('string'); // string, int, float, bool, json, array
            $table->unsignedBigInteger('crc')->nullable(); // CRC32 checksum of value
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
