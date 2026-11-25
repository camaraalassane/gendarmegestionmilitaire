<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Protection absolue : interdire formellement toute exécution de tests sur la base PostgreSQL réelle
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
            \Illuminate\Support\Facades\DB::purge();
        }

        parent::setUp();
    }
}
