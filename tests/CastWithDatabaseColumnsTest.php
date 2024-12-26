<?php

use MobileStock\LaravelDatabaseInterceptor\Middlewares\CastWithDatabaseColumns;

it('handles non-fetchAll statements correctly', function () {
    $middleware = new CastWithDatabaseColumns();
    $pdoData = [
        'stmt_method' => 'fetch',
        'stmt_call' => fn() => null,
    ];

    $result = $middleware->handle($pdoData, fn($data) => $data);

    expect($result)->toBe($pdoData);
});

it('processes fetchAll with scalar results correctly', function () {
    $middleware = new CastWithDatabaseColumns();
    $stmtCallMock = fn($method) => $method === 'getColumnMeta' ? ['name' => 'bool_isActive'] : null;

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [1, 0, 1];

    $result = $middleware->handle($pdoData, $next, 'bool');

    expect($result)->toBe([true, false, true]);
});

it('processes fetchAll with associative array results correctly', function () {
    $middleware = new CastWithDatabaseColumns();
    $stmtCallMock = fn($method) => $method === 'getColumnMeta' ? ['name' => 'bool_isActive'] : null;

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['bool_isActive' => 1, 'int_id' => '42'], ['bool_isActive' => 0, 'int_id' => '43']];

    $result = $middleware->handle($pdoData, $next);

    expect($result)->toBe([['isActive' => true, 'id' => 42], ['isActive' => false, 'id' => 43]]);
});

it('handles deeply nested JSON correctly', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn() => [
        'name' => 'field_json',
        'native_type' => 'VAR_STRING',
        'flags' => ['not_null'],
    ];

    $deepJson =
        '{"item1":{"item2":{"item3":{"item4":{"item5":{"item6":{"item7":{"item8":{"item9":{"item10":{"item11":{"item12":{"item13":{"item14":{"item15":{"item16":{"item17":{"item18":{"item19":{"item20":{"item21":{"item22":{"item23":{"item24":{"item25":{"item26":{"item27":{"item28":{"item29":{"item30":{"item31":{"item32":{"item33":{"item34":{"item35":{"item36":{"item37":{"item38":{"item39":{"item40":"value"}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}}';
    $expectedDecodedJson = json_decode($deepJson, true);

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['field_json' => $deepJson]];

    $result = $middleware->handle($pdoData, $next);

    expect($result)->toBe([['field' => $expectedDecodedJson]]);
});

it('fails to decode JSON exceeding depth limit', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn() => [
        'name' => 'field_json',
        'native_type' => 'VAR_STRING',
        'flags' => ['not_null'],
    ];

    $deepJson = '{"item":' . str_repeat('{"item":', 513) . '"is_value"' . str_repeat('}', 513);

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['field_json' => $deepJson]];

    $result = $middleware->handle($pdoData, $next, 'is');

    expect($result)->toBe([['field' => $deepJson]]);
});

it('processes integer casting correctly', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn() => [
        'name' => 'int_id',
        'native_type' => 'INT24',
        'flags' => ['not_null'],
    ];

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['int_id' => '1'], ['int_id' => '42']];

    $result = $middleware->handle($pdoData, $next);

    expect($result)->toBe([['id' => 1], ['id' => 42]]);
});

it('processes string casting correctly', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn($method, $arg) => [
        'name' => 'var_string',
        'native_type' => 'VAR_STRING',
        'flags' => ['not_null'],
    ];

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['var_string' => 'Hello'], ['var_string' => 'World']];

    $result = $middleware->handle($pdoData, $next);

    expect($result)->toBe([['var' => 'Hello'], ['var' => 'World']]);
    expect(array_keys($result[0]))->toBe(['var']);
    expect(array_keys($result[1]))->toBe(['var']);
});

it('processes boolean casting correctly', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn($method, $arg) => [
        'name' => 'is_active',
        'native_type' => 'TINY',
        'flags' => ['not_null'],
    ];

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['bool_isActive' => '1'], ['is_active' => '0']];

    $result = $middleware->handle($pdoData, $next, 'is', 'bool');

    expect($result)->toBe([['bool_isActive' => true], ['is_active' => false]]);
});

it('processes JSON casting correctly for valid JSON', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn($method, $arg) => [
        'name' => 'json_data',
        'native_type' => 'VAR_STRING',
        'flags' => ['not_null'],
    ];

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['json_data' => '{"key1":"value1","key2":"value2"}']];

    $result = $middleware->handle($pdoData, $next);

    expect($result)->toBe([['data' => ['key1' => 'value1', 'key2' => 'value2']]]);
});

it('processes JSON casting correctly for invalid JSON', function () {
    $middleware = new CastWithDatabaseColumns();

    $stmtCallMock = fn() => [
        'name' => 'json_data',
        'native_type' => 'VAR_STRING',
        'flags' => ['not_null'],
    ];

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => $stmtCallMock,
    ];

    $next = fn() => [['json_data' => '{"key1":"value1","key2":"value2"']]; // Invalid JSON

    $result = $middleware->handle($pdoData, $next);

    expect($result)->toBe([['data' => '{"key1":"value1","key2":"value2"']]);
});
