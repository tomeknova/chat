<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bootstrap the application for tests and FORCE an isolated test environment.
     *
     * `php artisan test` boots Laravel first (loading the local .env via putenv),
     * and the phpunit subprocess inherits those vars — so phpunit.xml <env> and a
     * .env.testing file do NOT bind reliably here. Forcing the config in code is
     * the only guarantee that tests run against an ephemeral sqlite :memory: DB and
     * NEVER touch the real mysql `chat` database. Mysql-parity runs go through the
     * dedicated Docker test env, not the daily local suite.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'url' => null,
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            // Stateless/fast drivers the phpunit.xml <env> intended but couldn't bind:
            // array cache avoids needing a DB `cache` table; sync queue runs inline.
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            'mail.default' => 'array',
            // Hermetic AI routing (full candidate set); escalation tests opt into lexical.
            'askdocs.retrieval.driver' => 'full',
        ]);

        return $app;
    }
}
