<?php

use Illuminate\Pipeline\Pipeline;
use MobileStock\LaravelDatabaseInterceptor\PdoInterceptorStatement;

function createPdoCastStatement(object $stmtParentMock, Closure $through): PdoInterceptorStatement
{
    $pipeline = new Pipeline();
    $pipeline->through($through);

    $pdoInterceptorStmtReflection = new ReflectionClass(PdoInterceptorStatement::class);
    $constructor = $pdoInterceptorStmtReflection->getConstructor();
    $constructor->setAccessible(true);
    $pdoCastStatementInstance = $pdoInterceptorStmtReflection->newInstanceWithoutConstructor();
    $constructor->invoke($pdoCastStatementInstance, $pipeline);

    $property = $pdoInterceptorStmtReflection->getProperty('parent');
    $property->setAccessible(true);
    $property->setValue($pdoCastStatementInstance, $stmtParentMock);
    return $pdoCastStatementInstance;
}

it('should execute the pipeline', function () {
    $stmtParentMock = new class {};

    $pdoCastStatement = createPdoCastStatement($stmtParentMock, function () {
        return ['test'];
    });

    expect($pdoCastStatement->fetchAll())->toBe(['test']);
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

dataset('stmtMethods', [
    'fetchAll' => [
        new class {
            public function fetchAll(): array
            {
                return ['test'];
            }
        },
        'fetchAll',
        ['test'],
    ],
    'execute' => [
        new class {
            public function execute(): bool
            {
                return true;
            }
        },
        'execute',
        true,
    ],
    'nextRowset' => [
        new class {
            public function nextRowset(): bool
            {
                return true;
            }
        },
        'nextRowset',
        true,
    ],
]);

it('should check stmt_method return', function (object $stmtParentMock, string $methodName, mixed $expected) {
    $pdoCastStatement = createPdoCastStatement($stmtParentMock, function (array $data, Closure $next) use (
        $methodName,
        $expected
    ) {
        expect($data['stmt_method'])->toBe($methodName);
        $result = $next($data);
        expect($result)->toBe($expected);
        return $result;
    });

    $pdoCastStatement->$methodName();
})->with('stmtMethods');
