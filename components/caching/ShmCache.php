<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components\caching;

use Yii;
use yii\caching\Cache;

/**
 * ShmCache
 * libshmcache is a local cache in the share memory for multi processes.
 * high performance due to read is lockless. libshmcache is 100+ times faster than a remote interface such as redis.
 * @link https://github.com/happyfish100/libshmcache
 */
class ShmCache extends Cache
{
    /**
     * @var string path
     */
    public $conf = '@app/config/libshmcache.conf';

    /**
     * @var \ShmCache the ShmCache instance
     */
    private $_cache;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->_cache = new \ShmCache(Yii::getAlias($this->conf), \ShmCache::SERIALIZER_IGBINARY);
    }

    /**
     * Get instance
     * @return \ShmCache
     */
    public function getCache()
    {
        return $this->_cache;
    }

    /**
     * mixed ShmCache::get(string $key[, boolean $returnExpired = false])
     * @param string $key: the key, must be a string variable
     * @return mixed value for success, false for key not exist or expired
     * @example: $value = $cache->get($key);
     */
    protected function getValue($key)
    {
        return $this->_cache->get($key);
    }

    /**
     * boolean ShmCache::set(string $key, mixed $value, long $ttl)
     * @param string $key: the key, must be a string variable
     * @param mixed $value: the value, any php variable
     * @param int $ttl: timeout / expire in seconds, such as 600 for ten minutes, ShmCache::NEVER_EXPIRED for never expired
     * @return bool true for success, false for fail
     * @throws \ShmCacheException if $value is false
     * @example: $cache->set($key, $value, 300);
     */
    protected function setValue($key, $value, $duration)
    {
        return $this->_cache->set($key, $value, $duration);
    }

    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * @param string $key: the key, must be a string variable
     * @param mixed $value: the value, any php variable
     * @param int $ttl: timeout / expire in seconds, such as 600 for ten minutes, ShmCache::NEVER_EXPIRED for never expired
     * @return bool true for success, false for fail
     * @throws \ShmCacheException if $value is false
     */
    protected function addValue($key, $value, $duration)
    {
        if ($this->exists($key)) {
            return false;
        }
        return $this->setValue($key, $value, $duration);
    }
    /**
     * long ShmCache::getExpires(string $key[, boolean $returnExpired = false])
     * get expires time as unix timestamp
     * @param string $key: the key, must be a string variable
     * @return bool timestamps such as 1483952635, 0 for never expired, false for not exist
     * @example $value = $cache->getExpires($key);
     */
    public function exists($key)
    {
        return $this->_cache->getExpires($this->buildKey($key)) !== false;
    }

    /**
     * boolean ShmCache::delete(string $key)
     * @param string $key the key of the value to be deleted
     * @return bool if no error happens during deletion
     */
    protected function deleteValue($key)
    {
        return $this->_cache->delete($key);
    }

    /**
     * boolean ShmCache::clear()
     * clear hashtable to empty. use this function carefully because it will remove all keys in cache
     * @return bool true for success, false for fail
     */
    protected function flushValues()
    {
        return $this->_cache->clear();
    }
}
