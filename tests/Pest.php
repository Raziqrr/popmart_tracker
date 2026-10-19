<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel app (container, facades, config) and never
| need a built frontend: withoutVite() stands in for the Vite manifest.
| Unit tests stay plain PHP.
*/
pest()->extend(TestCase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/*
| tests/Feature/Database runs against a real MySQL or MariaDB: the migrations
| use generated and spatial columns, so SQLite can't run them. They run when
| DB_CONNECTION=mysql is set (CI does, see .github/workflows/ci.yml) and skip
| everywhere else, so `php artisan test` stays green without a database.
*/
function usingMysql(): bool
{
    return ($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) === 'mysql';
}

if (usingMysql()) {
    pest()->use(RefreshDatabase::class)->in('Feature/Database');
} else {
    pest()->beforeEach(fn () => $this->markTestSkipped('Needs MySQL or MariaDB: set DB_CONNECTION=mysql and the DB_* values.'))
        ->in('Feature/Database');
}
