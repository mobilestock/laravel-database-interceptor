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
