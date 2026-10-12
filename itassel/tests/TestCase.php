<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->verifierBaseDeTest();

        parent::setUp();
    }

    /**
     * Avant RefreshDatabase : la suite ne peut pas vider une autre base.
     */
    private function verifierBaseDeTest(): void
    {
        $connexion = (string) config('database.default');
        $base = (string) config("database.connections.{$connexion}.database");

        if ($connexion !== 'mysql' || ! str_ends_with($base, '_test')) {
            throw new RuntimeException(
                "Les tests doivent tourner sur une base MySQL dont le nom finit par _test. Base actuelle : {$base}"
            );
        }
    }
}
