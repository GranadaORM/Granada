<?php

namespace Granada;

final class LazyItemCache
{
    /** @var array<string, array<mixed, mixed>> */
    private static array $_cache = [];

    /** @var array<int, array{0: string, 1: mixed}> */
    private static array $_keys = [];
    private static int $_max    = 500;

    public static function get(string $class, mixed $id): mixed
    {
        return self::$_cache[$class][$id] ?? null;
    }

    public static function set(string $class, mixed $id, mixed $model): void
    {
        if (self::size() >= self::$_max) {
            [$oldest_class, $oldest_id] = array_shift(self::$_keys);
            unset(self::$_cache[$oldest_class][$oldest_id]);
        }

        self::$_cache[$class][$id] = $model;
        self::$_keys[]             = [$class, $id];
    }

    public static function remove(string $class, mixed $id): void
    {
        unset(self::$_cache[$class][$id]);

        $idx = array_search([$class, $id], self::$_keys, true);
        if ($idx !== false) {
            array_splice(self::$_keys, $idx, 1);
        }
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
