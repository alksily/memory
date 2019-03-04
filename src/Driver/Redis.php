<?php

namespace AEngine\Slim\Memory\Driver;

use AEngine\Slim\Memory\CacheException;
use AEngine\Slim\Memory\Interfaces\DriverInterface;

class Redis implements DriverInterface
{
    protected $connection = null;

    /**
     * Create connection to the external storage server
     *
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @param array $options
     *
     * @throws CacheException
     */
    public function __construct($host, $port, $timeout, $options)
    {
        $this->connection = new \Redis;
        $this->connection->connect($host, $port, $timeout);
        $this->connection->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);

        foreach ($options as $key => $value) {
            $this->connection->setOption($key, $value);
        }

        if ($this->connection->ping() !== '+PONG') {
            throw new CacheException('Connecting to a cache server was unable');
        }
    }

    /**
     * Return value from external storage and returns
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->connection->get($key);
    }

    /**
     * Writes a value to an external storage key
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param string $tag
     *
     * @return bool
     */
    public function set($key, $value, $ttl = 0, $tag = null): bool
    {
        if ($tag) {
            $tags   = $this->get($tag);
            $tags   = $tags ? $tags : [];
            $tags[] = $key;
            $this->set($tag, array_unique($tags), $ttl);
        }

        $result = $this->connection->set($key, $value);
        if ($result && $ttl) {
            $this->connection->setTimeout($key, $ttl);
        }

        return $result;
    }

    /**
     * Removes specified key from the external storage
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete($key): bool
    {
        return !!$this->connection->delete($key);
    }

    /**
     * Remove all keys from an external storage
     *
     * @return bool
     */
    public function clear(): bool
    {
        return !!$this->connection->flushDB();
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array $keys
     *
     * @return array
     */
    public function getMultiple($keys): array
    {
        return $this->connection->mget($keys);
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
    public function setMultiple($values, $ttl = null, $tag = null): bool
    {
        if ($tag) {
            $tags = $this->get($tag);
            $tags = array_merge(($tags ? $tags : []), array_keys($values));
            $this->set($tag, array_unique($tags), $ttl);
        }

        $result = $this->connection->mset($values);
        if ($result && $ttl) {
            foreach ($values as $key => $value) {
                $this->connection->setTimeout($key, $ttl);
            }
        }

        return $result;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param array $keys
     *
     * @return bool
     */
    public function deleteMultiple($keys): bool
    {
        return !!$this->connection->delete($keys);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return $this->connection->exists($key);
    }

    /**
     * Return values for a given tag
     *
     * @param string $tag
     *
     * @return array
     */
    public function getByTag($tag): array
    {
        $data = [];

        if (($keys = $this->get($tag)) !== false) {
            $data = $this->connection->mget($keys);
        }

        return $data;
    }

    /**
     * Deletes values for a given tag
     *
     * @param string $tag
     *
     * @return bool
     */
    public function deleteByTag($tag): bool
    {
        if (($keys = $this->get($tag)) !== false) {
            $keys[] = $tag;

            return !!$this->connection->delete($keys);
        }

        return false;
    }
}
