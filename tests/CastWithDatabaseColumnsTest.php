<?php

use MobileStock\LaravelDatabaseInterceptor\Middlewares\CastWithDatabaseColumns;

beforeEach(function () {
    $this->middleware = new CastWithDatabaseColumns();
});

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

    $next = fn() => [['my_array' => [['foo', null, 42], ['bar']]], ['my_array' => null]];

    $result = $this->middleware->handle($pdoData, $next);

    expect($result[0])->toHaveKey('my_array');
});

dataset('commonData', [
    'should handle native type: LONG' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'int_id',
                'native_type' => 'LONG',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['int_id' => '42']],
        'expected' => [['id' => 42]],
    ],
    'should handle native type: LONGLONG' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'int_id',
                'native_type' => 'LONGLONG',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['int_id' => '42']],
        'expected' => [['id' => 42]],
    ],
    'should handle native type: SHORT' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'int_id',
                'native_type' => 'SHORT',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['int_id' => '42']],
        'expected' => [['id' => 42]],
    ],
    'should handle native type: TINY' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'int_id',
                'native_type' => 'TINY',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['int_id' => '42']],
        'expected' => [['id' => 42]],
    ],
    'should handle native type: INT24' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'int_id',
                'native_type' => 'INT24',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['int_id' => '42']],
        'expected' => [['id' => 42]],
    ],
    'should handle native type: YEAR' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'year',
                'native_type' => 'YEAR',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['year' => '2021']],
        'expected' => [['year' => 2021]],
    ],
    'should handle native type: FLOAT' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'float_value',
                'native_type' => 'FLOAT',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['float_value' => 3.14]],
        'expected' => [['value' => 3.14]],
    ],
    'should handle native type: DOUBLE' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'float_value',
                'native_type' => 'DOUBLE',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['float_value' => 3.14]],
        'expected' => [['value' => 3.14]],
    ],
    'should handle native type: NEWDECIMAL' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'float_value',
                'native_type' => 'NEWDECIMAL',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['float_value' => 3.14]],
        'expected' => [['value' => 3.14]],
    ],
    'should correctly cast internal static prefixes with not_null flag' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'var_string',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [
            [
                'bool_isActive' => 1,
                'int_id' => '42',
                'float_value' => '3.14',
                'var_string' => 'Hello',
                'field_json' => '{"isActive": true, "id": 42, "value": 3.14, "var": "Hello"}',
            ],
        ],
        'expected' => [
            [
                'isActive' => true,
                'id' => 42,
                'value' => 3.14,
                'var' => 'Hello',
                'field' => ['isActive' => true, 'id' => 42, 'value' => 3.14, 'var' => 'Hello'],
            ],
        ],
    ],
    'should correctly cast internal static prefixes with nullable flag' => [
        'pdoData' => [
            'stmt_call' => fn() => ['flag' => ['nullable']],
        ],
        'pdoResultMock' => [
            [
                'bool_isActive' => null,
                'int_id' => null,
                'float_value' => null,
                'var_string' => null,
                'field_json' => null,
            ],
        ],
        'expected' => [
            [
                'isActive' => false,
                'id' => 0,
                'value' => 0.0,
                'var' => '',
                'field' => null,
            ],
        ],
    ],
    'should handles JSON with 40 depths layers correctly' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'field_json',
                'native_type' => 'VAR_STRING',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['field_json' => str_repeat('{"item":', 40) . '"value"' . str_repeat('}', 40)]],
        'expected' => [['field' => json_decode(str_repeat('{"item":', 40) . '"value"' . str_repeat('}', 40), true)]],
    ],
    'should fails to decode JSON exceeding 803 depths layers limit' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'field_json',
                'native_type' => 'VAR_STRING',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [['field_json' => str_repeat('{"item":', 801) . '"value"' . str_repeat('}', 801) . '}']],
        'expected' => [['field' => str_repeat('{"item":', 801) . '"value"' . str_repeat('}', 801) . '}']],
    ],
    'should check non associative array to be a list' => [
        'pdoData' => [
            'stmt_call' => fn() => ['native_type' => 'STRING', 'flags' => []],
        ],
        'pdoResultMock' => [[['my_array' => [['foo', null, 42], ['bar']]], ['my_array' => null]]],
        'expected' => [[['my_array' => [['foo', null, 42], ['bar']]], ['my_array' => null]]],
    ],
    'should cast correctly with statics internal cast configuration a deep json object' => [
        'pdoData' => [
            'stmt_call' => fn() => [
                'name' => 'field_json',
                'native_type' => 'VAR_STRING',
                'flags' => ['not_null'],
            ],
        ],
        'pdoResultMock' => [
            [
                'field_json' =>
                    '{"firstBody": {"internalFirstBody": {"bool_isActive": 1, "int_id": "42"}}, "secondBody": {"float_value": "3.14", "var_string": "Hello"}, "thirdBody": {"field_json": "{\"isActive\": true, \"id\": 42, \"value\": 3.14, \"var\": \"Hello\"}"}}',
            ],
        ],
        'expected' => [
            [
                'field' => [
                    'firstBody' => [
                        'internalFirstBody' => ['isActive' => true, 'id' => 42],
                    ],
                    'secondBody' => ['value' => 3.14, 'var' => 'Hello'],
                    'thirdBody' => ['field' => ['isActive' => true, 'id' => 42, 'value' => 3.14, 'var' => 'Hello']],
                ],
            ],
        ],
    ],
]);

it('intend to resolve', function (array $pdoData, array $pdoResultMock, array $expected) {
    $pdoData['stmt_method'] = 'fetchAll';
    $next = fn() => $pdoResultMock;
    $result = $this->middleware->handle($pdoData, $next);
    expect($result)->toBe($expected);
})->with('commonData');
