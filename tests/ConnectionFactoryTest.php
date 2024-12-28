<?php

use Illuminate\Container\Container;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Facade;
use MobileStock\LaravelDatabaseInterceptor\ConnectionFactory;
use Mobilestock\LaravelDatabaseInterceptor\PdoInterceptorStatement;
use PDO;

if (!function_exists('app')) {
    function app($abstract = null)
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }
        return Container::getInstance()->make($abstract);
    }
}

beforeEach(function () {
    $this->container = new Container();
    Container::setInstance($this->container);

    $this->container->bind(Pipeline::class, function ($app) {
        return new Pipeline($app);
    });

    $configRepository = new ConfigRepository([
        'pdo-interceptor' => [
            'middlewares' => [],
        ],
    ]);
    $this->container->instance('config', $configRepository);

    Facade::setFacadeApplication($this->container);
});

it('should test the integration with database for real connection in memory', function () {
    $factory = new ConnectionFactory($this->container);

    $reflection = new ReflectionClass(ConnectionFactory::class);
    $method = $reflection->getMethod('createPdoResolver');
    $method->setAccessible(true);

    $resolver = $method->invoke($factory, [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    expect($resolver)->toBeInstanceOf(\Closure::class);

    $pdo = $resolver();
    expect($pdo)->toBeInstanceOf(PDO::class);

    $statementClass = $pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS);
    expect($statementClass[0])->toBe(PdoInterceptorStatement::class);
    expect($statementClass[1][0])->toBeInstanceOf(Pipeline::class);
});
