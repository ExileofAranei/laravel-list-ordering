<?php

namespace ExileOfAranei\ListOrdering\Tests;

use ExileOfAranei\ListOrdering\ListOrderingServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'ExileOfAranei\\ListOrdering\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Run after parent::setUp() (not in getEnvironmentSetUp()), which fires
        // before service providers boot — too early for the package's
        // Blueprint::orderingRank() macro to be registered yet.
        foreach ($this->fixtureMigrations() as $migration) {
            $migration->up();
        }
    }

    protected function tearDown(): void
    {
        // sqlite's ':memory:' database is naturally fresh per test, but a
        // real MySQL/MariaDB/PostgreSQL service (used to validate driver
        // collation) is a persistent server: without an explicit rollback,
        // every test after the first fails with "table already exists."
        foreach (array_reverse($this->fixtureMigrations()) as $migration) {
            $migration->down();
        }

        parent::tearDown();
    }

    /** @return list<Migration> */
    private function fixtureMigrations(): array
    {
        return array_map(
            fn ($file) => include $file->getRealPath(),
            iterator_to_array(File::allFiles(__DIR__.'/Fixtures/migrations')),
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ListOrderingServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        config()->set('database.default', 'testing');

        config()->set('database.connections.testing', $driver === 'sqlite' ? [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ] : [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT'),
            'database' => env('DB_DATABASE', 'testing'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
        ]);
    }
}
