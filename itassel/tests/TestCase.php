<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connexion = (string) config('database.default');
        if ($connexion !== 'mysql') {
            return;
        }

        $base = (string) config('database.connections.mysql.database');
        if (! str_ends_with($base, '_test')) {
            throw new \RuntimeException(
                "Refus d'exécuter les tests sur la base MySQL « {$base} ». Le nom doit se terminer par _test."
            );
        }
    }
}
