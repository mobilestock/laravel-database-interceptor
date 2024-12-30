<?php

use MobileStock\LaravelDatabaseInterceptor\PdoInterceptorStatement;
use Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function getStmt(...$args): PdoInterceptorStatement
{
    $reflectionClass = new ReflectionClass(PdoInterceptorStatement::class);
    $method = $reflectionClass->getConstructor();
    $method->setAccessible(true);
    $method->invoke($stmt = $reflectionClass->newInstanceWithoutConstructor(), ...$args);
    return $stmt;
}
