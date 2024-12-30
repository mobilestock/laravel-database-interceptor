<?php

namespace MobileStock\LaravelDatabaseInterceptor;

use Closure;
use Exception;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use ReflectionClass;
use RuntimeException;

class MysqlConnection extends \Illuminate\Database\MySqlConnection
{
    // @issue:https://github.com/mobilestock/backend/issues/726
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.

        $bindings = array_map(function (mixed $item): mixed {
            return is_array($item) ? json_encode($item) : $item;
        }, $bindings);

        try {
            return $callback($query, $bindings);
        } catch (Exception $e) {
            // If an exception occurs when attempting to run a query, we'll format the error
            // message to include the bindings with SQL, which will make this exception a
            // lot more helpful to the developer instead of just the database's errors.
            $reflectionClass = new ReflectionClass($e);

            if ($reflectionClass->isInternal()) {
                throw new QueryException($this->getName(), $query, $this->prepareBindings($bindings), $e);
            }

            throw $e;
        }
    }

    public function selectOneColumn($query, $bindings = [], $useReadPdo = true)
    {
        $this->changePdoFetchModeToFetchColumn();

        return self::selectOne($query, $bindings, $useReadPdo);
    }

    public function selectColumns($query, $bindings = [], $useReadPdo = true): array
    {
        $this->changePdoFetchModeToFetchColumn();

        return self::select($query, $bindings, $useReadPdo);
    }

    public function getLock(...$identifiers): void
    {
        if (DB::getPdo()->inTransaction()) {
            throw new RuntimeException('Cannot execute GET_LOCK within a transaction');
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4);
        $deepLayer = $backtrace[3];
        $shallowLayer = $backtrace[2];
        $layer = [
            'file' => $shallowLayer['file'],
            'class' => $deepLayer['class'] ?? '',
            'type' => $deepLayer['type'] ?? '',
            'function' => $deepLayer['function'],
            'args' => json_encode($shallowLayer['args']),
            'identifier' => json_encode($identifiers),
        ];
        $route = "{$layer['file']}::{$layer['class']}{$layer['type']}{$layer['function']}({$layer['args']})->getLock({$layer['identifier']})";
        $hashBacktrace = sha1($route);

        $this->selectOneColumn('SELECT GET_LOCK(:lock_id, 99999);', ['lock_id' => $hashBacktrace]);
    }

    protected function changePdoFetchModeToFetchColumn(): void
    {
        $listener = function (StatementPrepared $event) use (&$listener): bool {
            $this->getEventDispatcher()->forget(StatementPrepared::class, $listener);
            return $event->statement->setFetchMode(PDO::FETCH_COLUMN, 0) ?? false;
        };

        $this->getEventDispatcher()->listen(StatementPrepared::class, $listener);
    }
}
