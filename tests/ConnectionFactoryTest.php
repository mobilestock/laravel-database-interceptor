<?php

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\App;
use MobileStock\LaravelDatabaseInterceptor\ConnectionFactory;
use Mobilestock\LaravelDatabaseInterceptor\PdoInterceptorStatement;

it('should test the integration with database for real connection in memory', function () {
    $factory = new ConnectionFactory(App::getFacadeRoot());

    $reflection = new ReflectionClass($factory);
    $reflectionProperty = $reflection->getProperty('parent');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue(
        $factory,
        new class {
            public function createPdoResolver()
            {
                return function () {
                    $pdoMock = Mockery::mock(PDO::class);
                    $pdoMock->shouldReceive('setAttribute')->withArgs(function ($attribute, $value) {
                        expect($attribute)->toBe(PDO::ATTR_STATEMENT_CLASS);
                        expect($value[0])->toBe(PdoInterceptorStatement::class);
                        expect($value[1][0])->toBeInstanceOf(Pipeline::class);

                        return true;
                    });

                    return $pdoMock;
                };
            }
        }
    );

    $method = $reflection->getMethod('createPdoResolver');
    $method->setAccessible(true);

    $resolver = $method->invoke($factory, []);

    expect($resolver)->toBeInstanceOf(\Closure::class);

    $pdo = $resolver();
    expect($pdo)->toBeInstanceOf(PDO::class);
});
