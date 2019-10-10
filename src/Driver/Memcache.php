<?php

namespace Alksily\Memory\Driver;

use Alksily\Memory\CacheException;
use Alksily\Memory\Interfaces\DriverInterface;
use DateInterval;
use Memcached;

class Memcache implements DriverInterface
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
        $this->connection = new Memcached;
        $this->connection->addServer($host, $port);
        $this->connection->setOption(Memcached::OPT_CONNECT_TIMEOUT, $timeout * 1000);

        foreach ($options as $key => $value) {
            $this->connection->setOption($key, $value);
        }

        if (!$this->connection->getVersion()) {
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

        return $this->connection->set($key, $value, $ttl);
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
        return $this->connection->delete($key);
    }

    /**
     * Remove all keys from an external storage
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->connection->flush();
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
        return $this->connection->getMulti($keys);
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param array $values
     * @param null|int|DateInterval
     * @param null|string $tag
     *
     * @return bool
     *
     */
    public function setMultiple($values, $ttl = null, $tag = null): bool
    {
        if ($tag) {
            $tags = $this->get($tag);
            $tags = array_merge(($tags ? $tags : []), array_keys($values));
            $this->set($tag, array_unique($tags), $ttl);
        }

        return $this->connection->setMulti($values, $ttl);
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
        return $this->connection->deleteMulti($keys);
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
        $this->connection->get($key);

        return $this->connection->getResultCode() == Memcached::RES_SUCCESS;
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
            $data = $this->connection->getMulti($keys);
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

            $this->connection->deleteMulti($keys);

            return $this->connection->getResultCode() == Memcached::RES_SUCCESS;
        }

        return false;
    }
}
