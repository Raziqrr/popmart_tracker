<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE popmart_accounts MODIFY popmart_member_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE popmart_accounts MODIFY area CHAR(2) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE popmart_accounts MODIFY popmart_member_id VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE popmart_accounts MODIFY area CHAR(2) NOT NULL');
    }
};
