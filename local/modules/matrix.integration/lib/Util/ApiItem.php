<?php

namespace Matrix\Integration\Util;

/**
 * Унифицированное чтение полей из элементов ответа Matrix API (разные варианты имён полей).
 */
final class ApiItem
{
    /**
     * @param array<string, mixed> $row
     * @param string[] $keys
     */
    public static function int(array $row, int $default, string ...$keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if ($v === null || $v === '') {
                continue;
            }
            return (int) $v;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $keys
     */
    public static function string(array $row, string $default, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if ($v === null) {
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $keys
     * @return array<int|string, mixed>
     */
    public static function arrayList(array $row, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (empty($row[$key]) || !is_array($row[$key])) {
                continue;
            }

            return $row[$key];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $keys
     */
    public static function float(array $row, ?float $default, string ...$keys): ?float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if ($v === null || $v === '') {
                continue;
            }

            return (float) $v;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $keys
     */
    public static function bool(array $row, bool $default, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if ($v === true || $v === 1 || $v === '1' || $v === 'Y' || $v === 'y') {
                return true;
            }
            if ($v === false || $v === 0 || $v === '0' || $v === 'N' || $v === 'n') {
                return false;
            }
        }

        return $default;
    }
}
