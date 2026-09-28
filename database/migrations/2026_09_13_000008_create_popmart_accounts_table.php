<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popmart_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('popmart_member_id');
            $table->char('area', 2);
            $table->text('session_cookie'); // encrypted cast
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'popmart_member_id']);
            $table->index('popmart_member_id');
            $table->index('session_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popmart_accounts');
    }
};
