<?php

use MobileStock\LaravelDatabaseInterceptor\PdoInterceptorStatement;

class PdoInterceptorStatementWithParent extends PdoInterceptorStatement
{
    public $parent;
}

function getStmt(...$args): PdoInterceptorStatementWithParent
{
    $reflectionClass = new ReflectionClass(PdoInterceptorStatementWithParent::class);
    $method = $reflectionClass->getConstructor();
    $method->setAccessible(true);
    $method->invoke($stmt = $reflectionClass->newInstanceWithoutConstructor(), ...$args);
    return $stmt;
}
