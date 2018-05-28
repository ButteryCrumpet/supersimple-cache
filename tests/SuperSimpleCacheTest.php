<?php

use PHPUnit\Framework\TestCase;
use SuperSimpleCache\SuperSimpleCache;
use SuperSimpleCache\Exceptions\InvalidArgumentException;

class SuperSimpleCacheTest extends TestCase
{
    private $cache;
    private $key;
    private $value;
    private $expiredKey;
    private $tempDir;

    public function setUp()
    {
        $this->tempDir = dirname(__FILE__);
        $this->extension = ".cache";
        $this->cache = new SuperSimpleCache($this->tempDir);
        $this->value = new SuperSimpleCache($this->tempDir);
    }

    public function testGetGetsExisting()
    {
        $key = "key";
        $expiration = time() + (60 * 60 * 24);
        file_put_contents($this->tempDir . "/" . hash("md5", $key) . $this->extension, $expiration . PHP_EOL . serialize($this->value));
        $this->assertEquals($this->cache->get($key), $this->value);
        unlink($this->tempDir . "/" . hash("md5", $key) . $this->extension);
    }

    public function testGetReturnsDefaultIfKeyDoesNotExist()
    {
        $default = "default";
        $this->assertEquals($this->cache->get("notakey", $default), $default);
    }

    public function testGetReturnsDefaultIfExpired()
    {
        $old_time = time() - (60 * 60 * 24);
        $key = "expired";
        file_put_contents(
            $this->tempDir . "/" . hash("md5", $key) . $this->extension,
            $old_time . PHP_EOL . serialize($this->value)
        );
        $default = "default";
        $this->assertEquals($this->cache->get($key, $default), $default);
        unlink($this->tempDir . "/" . hash("md5", $key) . $this->extension);
    }

    public function testSetCorrectlySetsCacheItem()
    {
        $key = "set";
        $val = "some value";
        $this->assertTrue($this->cache->set($key, $val, new DateInterval("PT5M")));
        $this->assertFileExists($this->tempDir . "/" . hash("md5", $key) . $this->extension);
        unlink($this->tempDir . "/" . hash("md5", $key) . $this->extension);
    }

    public function testDeleteCorrectlyDeletesItem()
    {
        $key = "deleteMe";
        file_put_contents($this->tempDir . "/" . hash("md5", $key) . $this->extension, "deleteMe");
        $this->assertTrue($this->cache->delete($key));
    }

    public function testDeletesAllCache()
    {
        $files = ["test1","test2","test3"];
        foreach ($files as $file) {
            $this->cache->set($file, "item");
        }
        $this->cache->clear();
        $ok = true;
        foreach ($files as $file) {
            $ok = $ok && !file_exists($this->tempDir . "/" . hash("md5", $file) . $this->extension);
        }
        $this->assertTrue($ok);
    }

    public function testGetMultipleGetsCorrectly()
    {
        $keyVals = ["key1" => "val", "key2" => "val2"];
        foreach ($keyVals as $key => $val) {
            $this->cache->set($key, $val);
        }
        $this->assertEquals(
            $this->cache->getMultiple(["key1", "key2"], "fuck"),
            $keyVals
        );
        $this->cache->clear();
    }

    public function testHasCorrectlyReturnsTrueWhenCacheExists()
    {
        $key = "check me";
        file_put_contents($this->tempDir . "/" . hash("md5", $key) . $this->extension, "checkme");
        $this->assertTrue($this->cache->has($key));
        unlink($this->tempDir . "/" . hash("md5", $key) . $this->extension);
    }

    public function testHasCorrectlyReturnsFalseWhenCacheDoesNotExist()
    {
        $this->assertFalse($this->cache->has("non existing cache"));
    }

    /**
     * @expectedException SuperSimpleCache\Exceptions\InvalidArgumentException
     */
    public function testMultipleThrowExceptionWhenNotTraversable()
    {
        $this->cache->getMultiple("key");
    }

    /**
     * @expectedException SuperSimpleCache\Exceptions\InvalidArgumentException
     */
    public function testMethodsThrowExceptionWithInvalidKeyType()
    {
        $this->cache->get([]);
    }
}
