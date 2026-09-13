<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('store_id')->nullable(); // numeric internal id
            $table->char('area', 2);
            $table->string('ns_code')->nullable();
            $table->string('haiding_code')->nullable();
            $table->string('name');
            $table->string('local_name');
            $table->string('store_type'); // PHYSICAL, FLASH (pop-up)
            $table->string('business_status'); // OPEN, CLOSED, TEMPORARILY_CLOSED
            $table->boolean('pickup_enabled')->default(false);
            $table->text('address')->nullable();
            // MariaDB/older MySQL require SPATIAL-indexed columns to be NOT NULL;
            // (0,0) is used as the "unknown location" sentinel until real coords are scraped.
            $table->geometry('location', 'point')
                ->default(DB::raw("ST_GeomFromText('POINT(0 0)')"))
                ->comment('POINT(lng, lat)');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('business_cycle_start')->nullable(); // FLASH pop-ups only
            $table->timestamp('business_cycle_end')->nullable(); // FLASH pop-ups only
            $table->timestamps();

            $table->index(['area', 'business_status', 'pickup_enabled']);
            $table->index('store_type');
            $table->spatialIndex('location');
            $table->fullText(['name', 'local_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
