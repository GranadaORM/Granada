<?php

namespace Granada;

/**
 * @internal
 */
final class LazyItemCache
{
    /** @var array<string, array<string, array<mixed, array<string, mixed>>>> */
    private static array $_cache = [];

    /** @var array<int, array{0: string, 1: string, 2: mixed, 3: string}> */
    private static array $_keys      = [];
    private static int $_max         = 500;
    private static bool $_registered = false;

    /**
     * The row's cached entry, or null. With $relation given, only
     * that relation's entry matches; otherwise any relation's does.
     */
    public static function get(string $class, string $column, mixed $id, ?string $relation = null): mixed
    {
        $entries = self::$_cache[$class][$column][$id] ?? [];

        if ($relation !== null) {
            return $entries[$relation] ?? null;
        }

        $first = array_key_first($entries);

        return $first === null ? null : $entries[$first];
    }

    public static function set(string $class, string $column, mixed $id, mixed $model, string $relation): void
    {
        if (!self::$_registered) {
            ORM::_on_write(self::clear(...));
            self::$_registered = true;
        }

        if (self::size() >= self::$_max) {
            [$oldest_class, $oldest_column, $oldest_id, $oldest_relation] = array_shift(self::$_keys);
            unset(self::$_cache[$oldest_class][$oldest_column][$oldest_id][$oldest_relation]);
        }

        self::$_cache[$class][$column][$id][$relation] = $model;
        self::$_keys[]                                 = [$class, $column, $id, $relation];
    }

    /**
     * Remove a row's cached entries from every column and relation.
     */
    public static function remove(string $class, mixed $id): void
    {
        foreach (array_keys(self::$_cache[$class] ?? []) as $column) {
            unset(self::$_cache[$class][$column][$id]);
        }

        foreach (self::$_keys as $idx => $key) {
            if ($key[0] === $class && $key[2] === $id) {
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
