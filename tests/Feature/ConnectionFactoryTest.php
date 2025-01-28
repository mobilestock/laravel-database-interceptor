<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use MobileStock\LaravelDatabaseInterceptor\ConnectionFactory;
use MobileStock\LaravelDatabaseInterceptor\PdoInterceptorStatement;

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
                    $pdoMock
                        ->shouldReceive('setAttribute')
                        ->once()
                        ->withArgs(function ($attribute, $value) {
                            expect($attribute)->toBe(PDO::ATTR_STATEMENT_CLASS);
                            expect($value[0])->toBe(PdoInterceptorStatement::class);
                            expect($value[1][0])->toBeInstanceOf(Pipeline::class);

                            return true;
                        });

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
    $method->setAccessible(true);

    $resolver = $method->invoke($factory, []);

    expect($resolver)->toBeInstanceOf(Closure::class);

    $pdo = $resolver();
    expect($pdo)->toBeInstanceOf(PDO::class);
});

it('should not drop first layer event after calling DB::selectOneFirstColumn', function () {
    $eventCount = 0;

    Event::listen(StatementPrepared::class, function () use (&$eventCount) {
        $eventCount++;
    });

    DB::shouldReceive('selectOneFirstColumn')
        ->once()
        ->andReturnUsing(function () {
            $mockConnection = Mockery::mock(Connection::class);
            $mockStatement = Mockery::mock(PDOStatement::class);
            $mockStatement
                ->shouldReceive('setFetchMode')
                ->once()
                ->with(PDO::FETCH_ASSOC);

            Event::dispatch(new StatementPrepared($mockConnection, $mockStatement));

            return 'some_test_value';
        });

    DB::selectOneFirstColumn('SELECT 1');

    expect($eventCount)->toBe(1);

    $listenerId = \Closure::bind(
        function () {
            return spl_object_id($this->listeners[StatementPrepared::class][0]);
        },
        Event::getFacadeRoot(),
        get_class(Event::getFacadeRoot())
    )();

    expect($listenerId)->not->toBeNull();
});
