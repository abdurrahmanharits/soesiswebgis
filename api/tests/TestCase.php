<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase wipes the database, so refuse to boot against anything
     * but the test DB. Runs before any trait touches the database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        $database = $config->get('database.connections.'.$config->get('database.default').'.database');

        if ($database !== 'soesis_test') {
            throw new RuntimeException("Tests must run against the soesis_test database, not [{$database}].");
        }

        return $app;
    }
}
