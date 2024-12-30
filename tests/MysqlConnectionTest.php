<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use MobileStock\LaravelDatabaseInterceptor\MysqlConnection;

beforeEach(function () {
    $this->pdoMock = $this->createMock(PDO::class);
    $this->stmtMock = $this->createMock(PDOStatement::class);
});

it('should throw a Laravel error in case of syntax error', function () {
    $this->stmtMock
        ->method('execute')
        ->willThrowException(
            new PDOException(
                'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'INVALID SQL\' at line 1'
            )
        );
    $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

    $connection = new MysqlConnection($this->pdoMock);

    $connection->select('INVALID SQL');
})->throws(QueryException::class);

it('should throw a custom exception', function () {
    $customException = get_class(new class extends Exception {});
    $this->stmtMock->method('execute')->willThrowException(new $customException());
    $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

    $connection = new MysqlConnection($this->pdoMock);

    expect(fn() => $connection->select('INSERT INTO test (name) VALUES (?)', ['test']))->toThrow($customException);
});

it('should tests selectOneColumn', function () {
    $connection = new MysqlConnection($this->pdoMock);

    $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
    $this->stmtMock->method('fetchAll')->willReturn([1]);

    $connection->setEventDispatcher(Event::getFacadeRoot());
    $result = $connection->selectOneColumn('SELECT * FROM test');

    expect($result)->toBe(1);
});

it('should tests selectColumns', function () {
    $connection = new MysqlConnection($this->pdoMock);

    $this->pdoMock->method('prepare')->willReturn($this->stmtMock);
    $this->stmtMock->method('fetchAll')->willReturn([1, 2]);

    $connection->setEventDispatcher(Event::getFacadeRoot());
    $result = $connection->selectColumns('SELECT * FROM test');

    expect($result)->toBe([1, 2]);
});
