<?php

namespace Alksily\Memory;

use Alksily\Memory\Interfaces\DriverInterface;
use RuntimeException;

/**
 * Mem is user-friendly wrap around storage
 */
class Mem
{
    /**
     * Prevents reading from external storage
     *
     * @var bool
     */
    public static $disabled = false;

    /**
     * Prefix keys
     *
     * @var string
     */
    public static $prefix = '';

    /**
     * List of keys that are stored in the buffer
     *
     * @var array
     */
    public static $cachedKeys = [];

    /**
     * Internal storage
     *
     * @var array
     */
    protected static $buffer = [];

    /**
     * Array of connections
     *
     * @var array
     */
    protected static $connection = [
        'master' => [],
        'slave'  => [],
    ];

    /**
     * Setup the Memory driver
     *
     * @param array $configs
     *
     * @throws RuntimeException
     */
    public static function initialize(array $configs = [])
    {
        $default = [
            'driver'  => 'memcache',
            'host'    => '',
            'port'    => '',
            'timeout' => 10,
            'options' => [],
            'role'    => 'master',
        ];

        foreach ($configs as $index => $config) {
            $config = array_merge($default, $config);
            $role   = $config['role'] == 'master' ? 'master' : 'slave';

            switch (strtolower($config['driver'])) {
                case 'memcache':
                    static::$connection[$role][] = function () use ($config) {
                        return new \Alksily\Memory\Driver\Memcache(
                            $config['host'],
                            $config['port'],
                            $config['timeout'],
                            $config['options']
                        );
                    };
                    break;
                case 'redis':
                    static::$connection[$role][] = function () use ($config) {
                        return new \Alksily\Memory\Driver\Redis(
                            $config['host'],
                            $config['port'],
                            $config['timeout'],
                            $config['options']
                        );
                    };
                    break;
            }
        }

        return;
    }

    /**
     * Open and return a connection to external storage
     *
     * @param bool $useMaster
     *
     * @return DriverInterface
     * @throws CacheException
     */
    public static function getInstance($useMaster = false)
    {
        $pool = [];
        $role = $useMaster ? 'master' : 'slave';

        switch (true) {
            case !empty(static::$connection[$role]):
                $pool = static::$connection[$role];
                break;
            case !empty(static::$connection['master']):
                $pool = static::$connection['master'];
                $role = 'master';
                break;
            case !empty(static::$connection['slave']):
                $pool = static::$connection['slave'];
                $role = 'slave';
                break;
        }

        if ($pool) {
            if (is_array($pool)) {
                return static::$connection[$role] = $pool[array_rand($pool)]();
            } else {
                return $pool;
            }
        }

        throw new CacheException('Unable to establish connection');
    }

    /**
     * Generate key
     *
     * @param string $key
     *
     * @return string
     */
    protected static function getKey($key)
    {
        return static::$prefix ? static::$prefix . ':' . $key : $key;
    }

    /**
     * Return value from external storage and returns
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (!static::$disabled) {
            if (in_array($key, static::$buffer)) {
                $value = static::$buffer[$key];
            } else {
                $value = static::getInstance(false)->get(static::getKey($key));

                foreach (static::$cachedKeys as $k) {
                    if ($key == $k) {
                        static::$buffer[$key] = $value;
                    }
                }
            }

            return $value !== false ? $value : (is_callable($default) ? call_user_func($default) : $default);
        }

        return (is_callable($default) ? call_user_func($default) : $default);
    }

    /**
     * Writes a value to an external storage key
     *
     * @param string $key
     * @param mixed $value
     * @param null|int $ttl
     * @param string $tag
     *
     * @return bool
     *
     */
    public static function set($key, $value, $ttl = null, $tag = null)
    {
        if (isset(static::$cachedKeys[$key])) {
            unset(static::$buffer[$key]);
        }

        return static::getInstance(true)->set(static::getKey($key), $value, $ttl, static::getKey($tag));
    }

    /**
     * Removes specified key from the external storage
     *
     * @param string $key
     *
     * @return bool
     */
    public static function delete($key)
    {
        if (isset(static::$cachedKeys[$key])) {
            unset(static::$buffer[$key]);
        }

        return static::getInstance(true)->delete(static::getKey($key));
    }

    /**
     * Remove all keys from an external storage
     *
     * @return bool
     */
    public static function clear()
    {
        static::$buffer = [];

        return static::getInstance(true)->clear();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array $keys
     * @param mixed $default
     *
     * @return array
     */
    public static function getMultiple($keys, $default = null)
    {
        if (!static::$disabled) {
            $_keys  = [];
            $values = [];

            foreach ($keys as $index => $key) {
                if (isset(static::$buffer[$key])) {
                    $values[$key] = static::$buffer[$key];
                } else {
                    $_keys[$index] = static::getKey($key);
                }
            }

            $values = array_combine($keys, array_values(array_merge($values, static::getInstance(false)->getMultiple($_keys))));

            foreach ($values as $key => $value) {
                if (in_array($key, static::$cachedKeys) && !isset(static::$buffer[$key])) {
                    static::$buffer[$key] = $value;
                }
            }

            return !empty($values) ? $values : (is_callable($default) ? call_user_func($default) : $default);
        }

        return (is_callable($default) ? call_user_func($default) : $default);
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param array $values
     * @param null|int $ttl
     * @param null|string $tag
     *
     * @return bool
     */
    public static function setMultiple($values, $ttl = null, $tag = null)
    {
        $keys = [];
        foreach (array_keys($values) as $key) {
            if (isset(static::$cachedKeys[$key])) {
                unset(static::$buffer[$key]);
            }

            $keys[] = static::getKey($key);
        }

        return static::getInstance(true)->setMultiple(array_combine($keys, array_values($values)), $ttl, static::getKey($tag));
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param array $keys
     *
     * @return bool
     */
    public static function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            if (isset(static::$cachedKeys[$key])) {
                unset(static::$buffer[$key]);
            }
        }

        return static::getInstance(true)->deleteMultiple($keys);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has($key)
    {
        return static::getInstance(true)->has($key);
    }

    /**
     * Return values for a given tag
     *
     * @param string $tag
     *
     * @return array
     */
    public static function getByTag($tag)
    {
        return static::getInstance(false)->getByTag(static::getKey($tag));
    }

    /**
     * Deletes values for a given tag
     *
     * @param string $tag
     *
     * @return bool
     */
    public static function deleteByTag($tag)
    {
        return static::getInstance(true)->deleteByTag(static::getKey($tag));
    }
}
