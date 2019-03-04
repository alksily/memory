<?php

namespace AEngine\Slim\Memory\Interfaces;

interface DriverInterface
{
    /**
     * Return value from external storage and returns
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key);

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
    public function set($key, $value, $ttl = 0, $tag = null): bool;

    /**
     * Removes specified key from the external storage
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete($key): bool;

    /**
     * Remove all keys from an external storage
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array $keys
     *
     * @return array
     */
    public function getMultiple($keys): array;

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param array $values
     * @param null|int $ttl
     * @param null|string $tag
     *
     * @return bool
     */
    public function setMultiple($values, $ttl = null, $tag = null): bool;

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param array $keys
     *
     * @return bool
     */
    public function deleteMultiple($keys): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key): bool;

    /**
     * Return values for a given tag
     *
     * @param string $tag
     *
     * @return array
     */
    public function getByTag($tag): array;

    /**
     * Deletes values for a given tag
     *
     * @param string $tag
     *
     * @return bool
     */
    public function deleteByTag($tag): bool;
}
