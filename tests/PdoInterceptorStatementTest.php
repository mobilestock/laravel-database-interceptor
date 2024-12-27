<?php

use Illuminate\Pipeline\Pipeline;

it('should execute the pipeline', function () {
    $pipeline = new Pipeline();
    $pipeline->through(function () {
        expect(1)->toBe(1);
        return ['test'];
    });

    $pdoCastStatement = getStmt($pipeline);

    expect($pdoCastStatement->fetchAll())->toBe(['test']);
});

it('should provide correct data from PDO', function () {
    $stmt = new class {
        public function fetchAll(): array
        {
            return ['test'];
        }
    };

    $pipeline = new Pipeline();
    $pipeline->through(function (array $data, Closure $next) {
        expect($data['stmt_method'])->toBe('fetchAll');

        $result = $next($data);

        expect($result)->toBe(['test']);
        return $result;
    });

    $pdoCastStatement = getStmt($pipeline);
    $pdoCastStatement->parent = $stmt;

    $pdoCastStatement->fetchAll();
});

it('should call execute with correct pipeline data', function () {
    $stmtParentMock = new class {
        public function execute(): bool
        {
            return true;
        }
    };

    $pipeline = new Pipeline();
    $pipeline->through(function (array $data, Closure $next) {
        expect($data['stmt_method'])->toBe('execute');

        return $next($data);
    });

    $pdoCastStatement = getStmt($pipeline);
    $pdoCastStatement->parent = $stmtParentMock;

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

    $pipeline = new Pipeline();
    $pipeline->through(function (array $data, Closure $next) {
        $originalResult = $next($data);
        return false;
    });

    $pdoCastStatement = getStmt($pipeline);
    $pdoCastStatement->parent = $stmtParentMock;

    $result = $pdoCastStatement->nextRowset();

    expect($result)->toBeFalse();
});
