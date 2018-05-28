<?php

namespace SuperSimpleCache;

use Psr\SimpleCache\CacheInterface;
use SuperSimpleCache\Exceptions\InvalidArgumentException;

class SuperSimpleCache implements CacheInterface
{
    private $cacheDir;
    private $defaultTtl;
    private $extension = "cache";
    private $debug;

    /**
     * @param string $cacheDirectory The directory where cache files will be written
     * @param int $defaultTtl The default time to expiration (optional | default = 250)
     *
     * @return CacheInterface SuperSimpleCache
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $cacheDirectory is not a valid directory
     * @throws \Psr\SimpleCache\InvalidArgumentException When $cacheDirectory is not writable;
     */
    public function __construct($cacheDirectory, $defaultTtl = 250)
    {
        $cacheDirInfo = new \SplFileInfo($cacheDirectory);
        if (!$cacheDirInfo->isDir()) {
            throw new InvalidArgumentException("Cache directory is not a valid Directory");
        }
        if (!$cacheDirInfo->isWritable()) {
            throw new InvalidArgumentException("Cache directory must be writable");
        }

        $this->cacheDir = $cacheDirInfo->getRealPath() . DIRECTORY_SEPARATOR;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key The key of the requested cache
     * @param mixed $default The value returned when requested cache does not exist or has expired
     *
     * @return mixed $returns the value of the cache
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $key is not a legal value
     */
    public function get($key, $default = null)
    {
        $key = $this->generateKey($key);

        $fileInfo = new \SplFileInfo($this->buildPath($key));
        if (!$fileInfo->isFile()) {
            return $default;
        }
        $file = $fileInfo->openFile();
        $expiry = $file->fgets();
        // validate in here and throw cache exception if can't parse?
        if ($expiry < time()) {
            return $default;
        }

        $value = "";
        while (!$file->eof()) {
            $value .= $file->fgets();
        }
        
        $unserialized = unserialize($value);
        if ($unserialized === false && $value !== serialize(false)) {
            return $default;
        }
        return $unserialized;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the cache value to be set
     * @param mixed $value The value of the cache to be set
     * @param null|int|\DateInterval $ttl The time to expiration of the cache to be set (optional | default = null)
     *
     * @return bool True on success and false on failure
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $key is not a legal value
     */
    public function set($key, $value, $ttl = null)
    {
        $key = $this->generateKey($key);

        if (!($ttl instanceof \DateInterval || \is_integer($ttl) || $ttl === null)) {
            throw new InvalidArgumentException;
            return false;
        }

        if ($ttl === 0) {
            return $this->delete($key);
        }

        $expires = time() + $this->defaultTtl;
        if ($ttl instanceof \DateInterval) {
            $dateTime = new \DateTime;
            $dateTime->add($ttl);
            $expires = $dateTime->getTimestamp();
        }
        if (is_integer($ttl)) {
            $expires = time() + $ttl;
        }
 
        $file = new \SplFileObject($this->buildPath($key), "c");
        $contents = $expires . PHP_EOL . serialize($value);
        return $file->fwrite($contents) > 0;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The key of the cache value to be set
     *
     * @return bool True if the item was successfully removed. False if there was an error
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $key is not a legal value
     */
    public function delete($key)
    {
        $key = $this->generateKey($key);
        $location = $this->buildPath($key);
        return !file_exists($location) || unlink($location);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $ok = true;
        foreach (new \DirectoryIterator($this->cacheDir) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if ($fileInfo->isFile() && $fileInfo->getExtension() === $this->extension) {
                $ok = $ok && unlink($fileInfo->getRealPath());
            }
        }
        return $ok;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys of the requested caches
     * @param mixed $default The value returned if the requested caches do not exist or have expired
     * (optional | default = null)
     *
     * @return iterable A list of key => value pairs. Non-existent or expired keys will have the $default value
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $keys is neither and array nor traversable
     */
    public function getMultiple($keys, $default = null)
    {
        if (!($keys instanceof \Traversable || is_array($keys))) {
            throw new InvalidArgumentException("Keys must be an array or implement Traversable");
        }
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for each cache to be set.
     * @param null|int|\DateInterval $ttl The time to expiry for the group of caches (optional | default = null)
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $values is no an array or traversable
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!($values instanceof \Traversable || is_array($values))) {
            throw new InvalidArgumentException("Keys must be an array or implement Traversable");
        }
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $ok && $this->set($key, $value, $ttl);
        }
        return $ok;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of keys to be deleted form the cache.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $keys is not an array or traversable
     */
    public function deleteMultiple($keys)
    {
        if (!($keys instanceof \Traversable || is_array($keys))) {
            throw new InvalidArgumentException("Keys must be an array or implement Traversable");
        }
        $ok = true;
        foreach ($keys as $key) {
            $ok = $ok && $this->delete($key);
        }
        return $ok;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * In accordance with PSR-16 it is recommended that has() is only to be used for cache warming
     * type purposes and not to be used within live operations
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException When $key is not a legal value
     */
    public function has($key)
    {
        $key = $this->generateKey($key);
        $fileInfo = new \SplFileInfo($this->buildPath($key));
        return $fileInfo->isFile();
    }

    private function generateKey($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Key of type %s given. Must be a string of only word characters not including '.' and '-'",
                    gettype($key)
                )
            );
        }
        return \hash("md5", $key);
    }

    private function buildPath($key)
    {
        return $this->cacheDir . $key . "." . $this->extension;
    }
}
