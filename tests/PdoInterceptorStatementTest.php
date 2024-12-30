<?php

use Illuminate\Pipeline\Pipeline;
use Mobilestock\LaravelDatabaseInterceptor\PdoInterceptorStatement;

function createPdoCastStatement(object $stmtParentMock, Closure $through): PdoInterceptorStatement
{
    $pipeline = new Pipeline();
    $pipeline->through($through);

    $pdoCastStatement = getStmt($pipeline);
    $reflectionClass = new ReflectionClass($pdoCastStatement);
    $property = $reflectionClass->getProperty('parent');
    $property->setAccessible(true);
    $property->setValue($pdoCastStatement, $stmtParentMock);

    return $pdoCastStatement;
}

it('should execute the pipeline', function () {
    $stmtParentMock = new class {};

    $pdoCastStatement = createPdoCastStatement($stmtParentMock, function () {
        expect(1)->toBe(1);
        return ['test'];
    });

    expect($pdoCastStatement->fetchAll())->toBe(['test']);
});

it('should provide correct data from PDO', function () {
    $stmtParentMock = new class {
        public function fetchAll(): array
        {
            return ['test'];
        }
    };

    $pdoCastStatement = createPdoCastStatement($stmtParentMock, function (array $data, Closure $next) {
        expect($data['stmt_method'])->toBe('fetchAll');
        $result = $next($data);
        expect($result)->toBe(['test']);
        return $result;
    });

    $pdoCastStatement->fetchAll();
});

it('should call execute with correct pipeline data', function () {
    $stmtParentMock = new class {
        public function execute(): bool
        {
            return true;
        }
    };

    $pdoCastStatement = createPdoCastStatement($stmtParentMock, function (array $data, Closure $next) {
        expect($data['stmt_method'])->toBe('execute');
        return $next($data);
    });

    $result = $pdoCastStatement->execute(['foo' => 'bar']);
    expect($result)->toBeTrue();
});

it('should allow pipeline to modify nextRowset() result', function () {
    $stmtParentMock = new class {
        public function nextRowset(): bool
        {
            return true;
        }
    };

    $pdoCastStatement = createPdoCastStatement($stmtParentMock, function (array $data, Closure $next) {
        $next($data);
        return false;
    });

    $result = $pdoCastStatement->nextRowset();
    expect($result)->toBeFalse();
});
