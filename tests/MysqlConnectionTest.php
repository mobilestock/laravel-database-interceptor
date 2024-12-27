<?php

use Illuminate\Database\QueryException;
use MobileStock\LaravelDatabaseInterceptor\MysqlConnection;

it('should throw a Laravel error in case of syntax error', function () {
    $pdoMock = $this->createMock(PDO::class);
    $stmtMock = $this->createMock(PDOStatement::class);
    $stmtMock
        ->method('execute')
        ->willThrowException(
            new PDOException(
                'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'INVALID SQL\' at line 1'
            )
        );
    $pdoMock->method('prepare')->willReturn($stmtMock);

    $connection = new MysqlConnection($pdoMock);

    $connection->select('INVALID SQL');
})->throws(QueryException::class);

it('should throw a custom exception', function () {
    $customException = get_class(new class extends Exception {});

    $pdoMock = $this->createMock(PDO::class);
    $stmtMock = $this->createMock(PDOStatement::class);
    $stmtMock->method('execute')->willThrowException(new $customException());
    $pdoMock->method('prepare')->willReturn($stmtMock);

    $connection = new MysqlConnection($pdoMock);

    expect(fn() => $connection->select('INSERT INTO test (name) VALUES (?)', ['test']))->toThrow($customException);
});
