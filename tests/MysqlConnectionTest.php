<?php

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use MobileStock\LaravelDatabaseInterceptor\MysqlConnection;

beforeEach(function () {
    $this->pdoMock = Mockery::mock(PDO::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);
    $this->stmtMock->shouldReceive('setFetchMode');
});

it('should throw a Laravel error in case of syntax error', function () {
    $this->stmtMock
        ->shouldReceive('execute')
        ->andThrow(
            new PDOException(
                'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'INVALID SQL\' at line 1'
            )
        );
    $this->pdoMock->shouldReceive('prepare')->andReturn($this->stmtMock);

    $connection = new MysqlConnection($this->pdoMock);

    $connection->select('INVALID SQL');
})->throws(QueryException::class);

it('should throw a custom exception', function () {
    $customException = get_class(new class extends Exception {});
    $this->stmtMock->shouldReceive('execute')->andThrow(new $customException());
    $this->pdoMock->shouldReceive('prepare')->andReturn($this->stmtMock);

    $connection = new MysqlConnection($this->pdoMock);
    expect(fn() => $connection->select("INSERT INTO test (name) VALUES ('Gean')"))->toThrow($customException);
});

it('should return the single column value using selectOneColumn', function () {
    $connection = new MysqlConnection($this->pdoMock);

    $this->pdoMock->shouldReceive('prepare')->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchAll')->andReturn([1]);
    $this->stmtMock->shouldReceive('execute');
    $connection->setEventDispatcher(Event::getFacadeRoot());
    $result = $connection->selectOneColumn('SELECT * FROM test');

    expect($result)->toBe(1);
});

it('should return column values as a list when using selectColumns', function () {
    $connection = new MysqlConnection($this->pdoMock);

    $this->pdoMock->shouldReceive('prepare')->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchAll')->andReturn([1, 2]);
    $this->stmtMock->shouldReceive('execute');
    $connection->setEventDispatcher(Event::getFacadeRoot());
    $result = $connection->selectColumns('SELECT * FROM test');

    expect($result)->toBe([1, 2]);
});

it('should throws a exception if inside a transaction when calling getLock', function () {
    $conn = new MysqlConnection($this->pdoMock);
    DB::shouldReceive('getPdo')->andReturn($this->pdoMock);
    $this->pdoMock->shouldReceive('inTransaction')->andReturn(true);
    $conn->getLock('test_identifier');
})->throws(RuntimeException::class);

it('should executes getLock normally if not in a transaction', function () {
    $connectionMock = $this->createPartialMock(MysqlConnection::class, ['selectOneColumn']);
    $connectionMock->__construct($this->createMock(PDO::class));
    $connectionMock->method('selectOneColumn')->willReturn(1);

    $databaseManagerMock = $this->createPartialMock(DatabaseManager::class, ['connection']);
    $databaseManagerMock->method('connection')->willReturn($connectionMock);
    DB::swap($databaseManagerMock);

    DB::getLock('test_identifier');

    expect(true)->toBeTrue();
});

dataset('bindings', [
    'should change array to json string' => ['bindings' => ['key' => [1, 2, 3]], 'expected' => ['key' => '[1,2,3]']],
    'should not change provided string' => ['bindings' => ['value'], 'expected' => ['value']],
]);

it('intend to resolve', function (array $bindings, array $expected) {
    $connection = new MysqlConnection(Mockery::mock(PDO::class));
    $callback = fn($query, $bindings) => $bindings;

    $reflection = new ReflectionClass($connection);
    $method = $reflection->getMethod('runQueryCallback');
    $method->setAccessible(true);

    $result = $method->invokeArgs($connection, ['SELECT * FROM test', $bindings, $callback]);

    expect($result)->toBe($expected);
})->with('bindings');
