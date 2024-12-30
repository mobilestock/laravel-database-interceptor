<?php

namespace MobileStock\LaravelDatabaseInterceptor;

use Closure;
use Illuminate\Database\Connectors\ConnectionFactory as BaseConnectionFactory;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Config;
use MobileStock\LaravelDatabaseInterceptor\PdoInterceptorStatement;
use PDO;

class ConnectionFactory extends BaseConnectionFactory
{
    /**
     * @var string|object
     */
    private $parent = parent::class;

    protected function createPdoResolver(array $config): Closure
    {
        return function () use ($config): PDO {
            $connection = call_user_func([$this->parent, 'createPdoResolver'], $config)();

            $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [
                PdoInterceptorStatement::class,
                [app(Pipeline::class)->through(Config::get('pdo-interceptor.middlewares'))],
            ]);

            return $connection;
        };
    }
}
