<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Alerts (App\Services\Alerts): Laravel's notifications table is the in-app
// inbox, and product_events remembers which events were already handled.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('product_events', function (Blueprint $table) {
            // Set once popmart:dispatch-alerts has handled the event (alerted or not).
            $table->timestamp('alerted_at')->nullable()->after('occurred_at');
            $table->index(['alerted_at', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('product_events', function (Blueprint $table) {
            $table->dropIndex(['alerted_at', 'occurred_at']);
            $table->dropColumn('alerted_at');
        });

        Schema::dropIfExists('notifications');
    }
};
