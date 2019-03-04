<?php

namespace AEngine\Slim\Memory;

use AEngine\Slim\Memory\Interfaces\DriverInterface;
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
    public $disabled = false;

    /**
     * Prefix keys
     *
     * @var string
     */
    public $prefix = '';

    /**
     * List of keys that are stored in the buffer
     *
     * @var array
     */
    public $cachedKeys = [];

    /**
     * Internal storage
     *
     * @var array
     */
    protected $buffer = [];

    /**
     * Array of connections
     *
     * @var array
     */
    protected $connection = [
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
    public function __construct(array $configs = [])
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
                    $this->connection[$role][] = function () use ($config) {
                        return new \AEngine\Slim\Memory\Driver\Memcache(
                            $config['host'],
                            $config['port'],
                            $config['timeout'],
                            $config['options']
                        );
                    };
                    break;
                case 'redis':
                    $this->connection[$role][] = function () use ($config) {
                        return new \AEngine\Slim\Memory\Driver\Redis(
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
    public function getInstance($useMaster = false)
    {
        $pool = [];
        $role = $useMaster ? 'master' : 'slave';

        switch (true) {
            case !empty($this->connection[$role]):
                $pool = $this->connection[$role];
                break;
            case !empty($this->connection['master']):
                $pool = $this->connection['master'];
                $role = 'master';
                break;
            case !empty($this->connection['slave']):
                $pool = $this->connection['slave'];
                $role = 'slave';
                break;
        }

        if ($pool) {
            if (is_array($pool)) {
                return $this->connection[$role] = $pool[array_rand($pool)]();
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
    protected function getKey($key)
    {
        return $this->prefix ? $this->prefix . ':' . $key : $key;
    }

    /**
     * Return value from external storage and returns
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!$this->disabled) {
            if (in_array($key, $this->buffer)) {
                $value = $this->buffer[$key];
            } else {
                $value = $this->getInstance(false)->get($this->getKey($key));

                foreach ($this->cachedKeys as $k) {
                    if ($key == $k) {
                        $this->buffer[$key] = $value;
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
    public function set($key, $value, $ttl = null, $tag = null)
    {
        if (isset($this->cachedKeys[$key])) {
            unset($this->buffer[$key]);
        }

        return $this->getInstance(true)->set($this->getKey($key), $value, $ttl, $this->getKey($tag));
    }

    /**
     * Removes specified key from the external storage
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete($key)
    {
        if (isset($this->cachedKeys[$key])) {
            unset($this->buffer[$key]);
        }

        return $this->getInstance(true)->delete($this->getKey($key));
    }

    /**
     * Remove all keys from an external storage
     *
     * @return bool
     */
    public function clear()
    {
        $this->buffer = [];

        return $this->getInstance(true)->clear();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array $keys
     * @param mixed $default
     *
     * @return array
     */
    public function getMultiple($keys, $default = null)
    {
        if (!$this->disabled) {
            $_keys  = [];
            $values = [];

            foreach ($keys as $index => $key) {
                if (isset($this->buffer[$key])) {
                    $values[$key] = $this->buffer[$key];
                } else {
                    $_keys[$index] = $this->getKey($key);
                }
            }

            $values = array_combine($keys, array_values(array_merge($values, $this->getInstance(false)->getMultiple($_keys))));

            foreach ($values as $key => $value) {
                if (in_array($key, $this->cachedKeys) && !isset($this->buffer[$key])) {
                    $this->buffer[$key] = $value;
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
    public function setMultiple($values, $ttl = null, $tag = null)
    {
        $keys = [];
        foreach (array_keys($values) as $key) {
            if (isset($this->cachedKeys[$key])) {
                unset($this->buffer[$key]);
            }

            $keys[] = $this->getKey($key);
        }

        return $this->getInstance(true)->setMultiple(array_combine($keys, array_values($values)), $ttl, $this->getKey($tag));
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param array $keys
     *
     * @return bool
     */
    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            if (isset($this->cachedKeys[$key])) {
                unset($this->buffer[$key]);
            }
        }

        return $this->getInstance(true)->deleteMultiple($keys);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->getInstance(true)->has($key);
    }

    /**
     * Return values for a given tag
     *
     * @param string $tag
     *
     * @return array
     */
    public function getByTag($tag)
    {
        return $this->getInstance(false)->getByTag($this->getKey($tag));
    }

    /**
     * Deletes values for a given tag
     *
     * @param string $tag
     *
     * @return bool
     */
    public function deleteByTag($tag)
    {
        return $this->getInstance(true)->deleteByTag($this->getKey($tag));
    }
}
