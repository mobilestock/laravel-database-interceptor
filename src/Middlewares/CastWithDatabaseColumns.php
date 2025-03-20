<?php

namespace MobileStock\LaravelDatabaseInterceptor\Middlewares;

use Closure;

class CastWithDatabaseColumns
{
    protected array $columnCache = [];
    protected Closure $stmtCall;
    protected array $booleanPrefixTerms = [];

    public function handle(array $pdoData, callable $next, string ...$booleanPrefixTerms): mixed
    {
        $this->booleanPrefixTerms = $booleanPrefixTerms;
        if ($pdoData['stmt_method'] !== 'fetchAll') {
            return $next($pdoData);
        }
        $this->columnCache = [];

        $result = $next($pdoData);
        $this->stmtCall = $pdoData['stmt_call'];

        if (array_is_list($result) && array_key_exists(0, $result) && (is_scalar($result[0]) || is_null($result[0]))) {
            $column = ($this->stmtCall)('getColumnMeta', 0)['name'];

            foreach ($result as &$item) {
                [, $item] = $this->castValue(0, $item, $column);
            }
        } else {
            foreach ($result as &$data) {
                $data = $this->castAssoc($data, null);
            }
        }

        return $result;
    }

    protected function castValue(int $key, $value, string $columnName): array
    {
        if (isset($this->columnCache[$columnName])) {
            [$columnName, $castFunction] = $this->columnCache[$columnName];
            $value = $castFunction($value);

            return [$columnName, $value];
        }

        $pointPosition = mb_strrpos($columnName, '|', -1);
        $activeColumnName = mb_substr($columnName, $pointPosition ? $pointPosition + 1 : 0);

        foreach ($this->booleanPrefixTerms as $prefix) {
            if (!str_starts_with($activeColumnName, $prefix . '_')) {
                continue;
            }

            $this->columnCache[$columnName] = [$activeColumnName, 'boolval'];

            return $this->castValue($key, $value, $columnName);
        }

        foreach (
            [
                ['bool', 5, 'boolval'],
                ['int', 4, 'intval'],
                ['float', 6, 'floatval'],
                ['string', 7, 'strval'],
                ['json', 5, [static::class, 'jsonval']],
            ]
            as $alias
        ) {
            if (str_starts_with($activeColumnName, $alias[0] . '_')) {
                $this->columnCache[$columnName] = [mb_substr($activeColumnName, $alias[1]), $alias[2]];

                return $this->castValue($key, $value, $columnName);
            } elseif (str_ends_with($activeColumnName, '_' . $alias[0])) {
                $this->columnCache[$columnName] = [mb_substr($activeColumnName, 0, -$alias[1]), $alias[2]];

                return $this->castValue($key, $value, $columnName);
            }
        }

        if ($this->isAssociative($value)) {
            $this->columnCache[$columnName] = [$activeColumnName, fn($value) => $this->castAssoc($value, $columnName)];

            return $this->castValue($key, $value, $columnName);
        } elseif (is_array($value) && array_is_list($value)) {
            $this->columnCache[$columnName] = [
                $activeColumnName,
                fn($value) => is_null($value)
                    ? null
                    : array_map(fn($value) => $this->castAssoc($value, $columnName), $value),
            ];

            return $this->castValue($key, $value, $columnName);
        }

        if ($columnName !== $activeColumnName) {
            $this->columnCache[$columnName] = [$activeColumnName, fn($value) => $value];

            return $this->castValue($key, $value, $columnName);
        }

        $columnMeta = ($this->stmtCall)('getColumnMeta', $key);
        $columnMeta['native_type'] ??= 'STRING';
        switch ($columnMeta['native_type']) {
            case 'LONG':
            case 'LONGLONG':
            case 'SHORT':
            case 'TINY':
            case 'INT24':
            case 'YEAR':
                $this->columnCache[$columnName] = [$columnName, 'intval'];
                break;
            case 'FLOAT':
            case 'DOUBLE':
            case 'NEWDECIMAL':
                $this->columnCache[$columnName] = [$columnName, 'floatval'];
                break;
            default:
                $this->columnCache[$columnName] = [$columnName, fn($value) => $value];
        }

        if (!in_array('not_null', $columnMeta['flags'] ?? [])) {
            $previousFunction = $this->columnCache[$columnName][1];
            $this->columnCache[$columnName] = [
                $columnName,
                fn($value) => is_null($value) ? null : $previousFunction($value),
            ];
        }

        return $this->castValue($key, $value, $columnName);
    }

    protected static function jsonval(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $result = json_decode($value, true, 802);

        if (json_last_error()) {
            return $value;
        }

        return $result;
    }

    public function castAssoc(mixed $data, ?string $columnBase): mixed
    {
        if (!$this->isAssociative($data)) {
            return $data;
        }

        $key = 0;

        for ($i = 0; $i < count($data); $i++) {
            $column = array_keys($data)[$i];
            $value = &$data[$column];

            [$newColumn, $value] = $this->castValue($key, $value, !$columnBase ? $column : "$columnBase|" . $column);

            if (!$columnBase) {
                $key++;
            }
            if ($column !== $newColumn) {
                unset($data[$column]);
                $data[$newColumn] = $value;
                $i--;
            }
        }

        return $data;
    }

    protected function isAssociative(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return !array_is_list($value);
    }
}
