<?php

use MobileStock\LaravelDatabaseInterceptor\Middlewares\CastWithDatabaseColumns;

beforeEach(function () {
    $this->middleware = new CastWithDatabaseColumns();
});

// TODO: Utilizar data sets para evitar código duplicado: https://pestphp.com/docs/datasets
it('should repass to next middleware if non-fetchAll statement was called', function () {
    $pdoData = [
        'stmt_method' => 'fetch',
    ];

    $result = $this->middleware->handle($pdoData, fn($data) => $data);

    expect($result)->toBe($pdoData);
});

it(
    'should cast bool values correctly by injecting a custom prefix when bool is already a static internal cast configuration',
    function () {
        $pdoData = [
            'stmt_method' => 'fetchAll',
            'stmt_call' => fn() => ['name' => 'bool_isActive'],
        ];

        $next = fn() => [1, 0, 1];

        $result = $this->middleware->handle($pdoData, $next, 'bool');

        expect($result)->toBe([true, false, true]);
    }
);

it('should correctly cast internal static prefixes with not_null flag', function () {
    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => ['flag' => ['not_null']],
    ];

    $next = fn() => [
        [
            'bool_isActive' => 1,
            'int_id' => '42',
            'float_value' => '3.14',
            'var_string' => 'Hello',
            'field_json' => '{"isActive": true, "id": 42, "value": 3.14, "var": "Hello"}',
        ],
    ];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result)->toBe([
        [
            'isActive' => true,
            'id' => 42,
            'value' => 3.14,
            'var' => 'Hello',
            'field' => ['isActive' => true, 'id' => 42, 'value' => 3.14, 'var' => 'Hello'],
        ],
    ]);
});

it('should correctly cast internal static prefixes with nullable flag', function () {
    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => ['flag' => ['nullable']],
    ];

    $next = fn() => [
        [
            'bool_isActive' => null,
            'int_id' => null,
            'float_value' => null,
            'var_string' => null,
            'field_json' => null,
        ],
    ];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result)->toBe([
        [
            'isActive' => false,
            'id' => 0,
            'value' => 0.0,
            'var' => '',
            'field' => null,
        ],
    ]);
});

it('should handles JSON with 40 depths layers correctly', function () {
    $deepJson = str_repeat('{"item":', 40) . '"value"' . str_repeat('}', 40);
    $expectedDecodedJson = json_decode($deepJson, true);

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => [
            'name' => 'field_json',
            'native_type' => 'VAR_STRING',
            'flags' => ['not_null'],
        ],
    ];

    $next = fn() => [['field_json' => $deepJson]];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result)->toBe([['field' => $expectedDecodedJson]]);
});

it('should fails to decode JSON exceeding 803 depths layers limit', function () {
    $deepJson = '{"item":' . str_repeat('{"item":', 801) . '"value"' . str_repeat('}', 801) . '}';

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => [
            'name' => 'field_json',
            'native_type' => 'VAR_STRING',
            'flags' => ['not_null'],
        ],
    ];

    $next = fn() => [['field_json' => $deepJson]];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result)->toBe([['field' => $deepJson]]);
});

// fetch all - bool - nullable - sem sufixo _bool
// fetch all - bool - nullable - com sufixo _bool
// fetch all - bool - nullable - com sufixo _bool
// fetch all json - bool - com sufixo _bool
// fetch all json - bool - sem sufixo _bool

it('should cast boolean correctly with custom prefix and not_null flag', function () {
    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => [
            'name' => 'is_active',
            'native_type' => 'TINY',
            'flags' => ['not_null'],
        ],
    ];

    $next = fn() => [['bool_isActive' => '1'], ['is_active' => '0']];

    $result = $this->middleware->handle($pdoData, $next, 'is', 'bool');

    expect($result)->toBe([['bool_isActive' => true], ['is_active' => false]]);
});

it('should cast boolean correctly with custom prefix and nullable flag', function () {
    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => [
            'name' => 'is_active',
            'native_type' => 'TINY',
            'flags' => ['nullable'],
        ],
    ];

    $next = fn() => [['bool_isActive' => '1'], ['is_active' => null]];

    $result = $this->middleware->handle($pdoData, $next, 'is', 'bool');

    expect($result)->toBe([['bool_isActive' => true], ['is_active' => false]]);
});

it('should check non associative array to be a list', function () {
    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => ['native_type' => 'STRING', 'flags' => []],
    ];

    $next = fn() => [['my_array' => [['foo'], ['bar']]]];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result[0])->toHaveKey('my_array');
});

it('should cast correctly with statics internal cast configuration a deep json object', function () {
    $deepJson =
        '{"firstBody": {"internalFirstBody": {"bool_isActive": 1, "int_id": "42"}}, "secondBody": {"float_value": "3.14", "var_string": "Hello"}, "thirdBody": {"field_json": "{\"isActive\": true, \"id\": 42, \"value\": 3.14, \"var\": \"Hello\"}"}}';

    $pdoData = [
        'stmt_method' => 'fetchAll',
        'stmt_call' => fn() => [
            'name' => 'field_json',
            'native_type' => 'VAR_STRING',
            'flags' => ['not_null'],
        ],
    ];

    $next = fn() => [['field_json' => $deepJson]];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result)->toBe([
        [
            'field' => [
                'firstBody' => [
                    'internalFirstBody' => ['isActive' => true, 'id' => 42],
                ],
                'secondBody' => ['value' => 3.14, 'var' => 'Hello'],
                'thirdBody' => ['field' => ['isActive' => true, 'id' => 42, 'value' => 3.14, 'var' => 'Hello']],
            ],
        ],
    ]);
});
