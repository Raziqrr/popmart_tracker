<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Collection discovery (App\Services\PopMart\Sync\CollectionDiscovery). A
// collection found through a product or CMS payload may come without its
// theme (ipId) or name, so theme_id becomes optional and names can be
// placeholders until a real one shows up.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropForeign(['theme_id']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->uuid('theme_id')->nullable()->change();
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();

            // False while `name` is a placeholder ("Collection 1a2b3c4d").
            $table->boolean('name_confirmed')->default(true)->after('slug');
            // How we learned about it: seed, product, cms, manual.
            $table->string('source')->default('seed')->after('name_confirmed');
            // The sweep visits the least recently swept collections first.
            $table->timestamp('last_swept_at')->nullable()->after('source');

            $table->index('last_swept_at');
        });

        Schema::table('themes', function (Blueprint $table) {
            $table->boolean('name_confirmed')->default(true)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn('name_confirmed');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['last_swept_at']);
            $table->dropColumn(['name_confirmed', 'source', 'last_swept_at']);
            $table->dropForeign(['theme_id']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->uuid('theme_id')->nullable(false)->change();
            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
        });
    }
};
