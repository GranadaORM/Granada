<?php

namespace Granada;

/**
 * @internal
 */
final class LazyItemCache
{
    /** @var array<string, array<string, array<mixed, mixed>>> */
    private static array $_cache = [];

    /** @var array<int, array{0: string, 1: string, 2: mixed}> */
    private static array $_keys      = [];
    private static int $_max         = 500;
    private static bool $_registered = false;

    public static function get(string $class, string $column, mixed $id): mixed
    {
        return self::$_cache[$class][$column][$id] ?? null;
    }

    public static function set(string $class, string $column, mixed $id, mixed $model): void
    {
        if (!self::$_registered) {
            ORM::_on_write(self::clear(...));
            self::$_registered = true;
        }

        if (self::size() >= self::$_max) {
            [$oldest_class, $oldest_column, $oldest_id] = array_shift(self::$_keys);
            unset(self::$_cache[$oldest_class][$oldest_column][$oldest_id]);
        }

        self::$_cache[$class][$column][$id] = $model;
        self::$_keys[]                      = [$class, $column, $id];
    }

    /**
     * Drop a row's cached entries, under every column it is cached by.
     */
    public static function remove(string $class, mixed $id): void
    {
        foreach (array_keys(self::$_cache[$class] ?? []) as $column) {
            unset(self::$_cache[$class][$column][$id]);
        }

        foreach (self::$_keys as $idx => [$key_class, $_, $key_id]) {
            if ($key_class === $class && $key_id === $id) {
                unset(self::$_keys[$idx]);
            }
        }
        self::$_keys = array_values(self::$_keys);
    }

    public static function clear(): void
    {
        self::$_cache = [];
        self::$_keys  = [];
    }

    public static function size(): int
    {
        return count(self::$_keys);
    }

    public static function setMax(int $max): void
    {
        self::$_max = $max;
    }
}
