<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');

        foreach (File::allFiles(__DIR__.'/../workbench/database/Migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
