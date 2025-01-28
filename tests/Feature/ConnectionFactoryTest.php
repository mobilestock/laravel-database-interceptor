<?php

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\App;
use MobileStock\LaravelDatabaseInterceptor\ConnectionFactory;
use MobileStock\LaravelDatabaseInterceptor\PdoInterceptorStatement;

it('should test the integration with database for real connection in memory', function () {
    $factory = new ConnectionFactory(App::getFacadeRoot());

    $reflection = new ReflectionClass($factory);
    $reflectionProperty = $reflection->getProperty('parent');
    $reflectionProperty->setValue(
        $factory,
        new class {
            public function createPdoResolver()
            {
                return function () {
                    $pdoMock = Mockery::mock(PDO::class);
                    $pdoMock
                        ->shouldReceive('setAttribute')
                        ->withArgs(function ($attribute, $value) {
                            expect($attribute)
                                ->toBe(PDO::ATTR_STATEMENT_CLASS)
                                ->and($value[0])
                                ->toBe(PdoInterceptorStatement::class)
                                ->and($value[1][0])
                                ->toBeInstanceOf(Pipeline::class);

                            return true;
                        })
                        ->once();

                    $pdoMock
                        ->shouldReceive('setAttribute')
                        ->once()
                        ->with(PDO::ATTR_EMULATE_PREPARES, true);

                    return $pdoMock;
                };
            }
        }
    );

    $method = $reflection->getMethod('createPdoResolver');

    $resolver = $method->invoke($factory, []);

    expect($resolver)->toBeInstanceOf(Closure::class);

    $pdo = $resolver();
    expect($pdo)->toBeInstanceOf(PDO::class);
});
