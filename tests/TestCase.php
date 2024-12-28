<?php

namespace Tests;

use MobileStock\LaravelDatabaseInterceptor\DatabaseInterceptorServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [DatabaseInterceptorServiceProvider::class];
    }
}
