<?php

namespace Granada;

class LazyItemCache
{
    private static $_cache = [];
    private static $_keys  = [];
    private static $_max   = 500;

    public static function get($class, $id)
    {
        return self::$_cache[$class][$id] ?? null;
    }

    public static function set($class, $id, $model)
    {
        if (self::size() >= self::$_max) {
            [$oldest_class, $oldest_id] = array_shift(self::$_keys);
            unset(self::$_cache[$oldest_class][$oldest_id]);
        }

        self::$_cache[$class][$id] = $model;
        self::$_keys[]             = [$class, $id];
    }

    public static function remove($class, $id)
    {
        unset(self::$_cache[$class][$id]);

        $idx = array_search([$class, $id], self::$_keys);
        if ($idx !== false) {
            array_splice(self::$_keys, $idx, 1);
        }
    }

    public static function clear()
    {
        self::$_cache = [];
        self::$_keys  = [];
    }

    public static function size()
    {
        return count(self::$_keys);
    }

    public static function setMax($max)
    {
        self::$_max = $max;
    }
}
