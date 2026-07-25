<?php

namespace ExileOfAranei\ListOrdering\Tests;

use ExileOfAranei\ListOrdering\ListOrderingServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
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
        foreach (File::allFiles(__DIR__.'/Fixtures/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            ListOrderingServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
