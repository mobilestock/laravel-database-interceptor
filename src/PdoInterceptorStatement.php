<?php

namespace Mobilestock\LaravelDatabaseInterceptor;

use PDOStatement;
use PDO;
use Illuminate\Contracts\Pipeline\Pipeline;

class PdoInterceptorStatement extends PDOStatement
{
    protected Pipeline $pipeline;
    /**
     * @var string|object
     */
    private $parent = parent::class;

    private function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    public function call(string $methodName, array $args): mixed
    {
        $statementCall = function (string $methodName, ...$args) {
            return call_user_func_array([$this->parent, $methodName], $args);
        };

        $result = $this->pipeline
            ->send([
                'stmt_method' => $methodName,
                'stmt_call' => $statementCall,
            ])
            ->then(fn() => $statementCall($methodName, ...$args));

        return $result;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->call('fetchAll', func_get_args());
    }

    public function execute(?array $params = null): bool
    {
        return $this->call('execute', func_get_args());
    }

    public function nextRowset(): bool
    {
        return $this->call('nextRowset', func_get_args());
    }
}
