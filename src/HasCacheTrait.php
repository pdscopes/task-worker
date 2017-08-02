<?php

namespace MadeSimple\TaskWorker;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Class HasCacheTrait
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
trait HasCacheTrait
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @param CacheItemPoolInterface $cache
     */
    public function setCache(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }
}