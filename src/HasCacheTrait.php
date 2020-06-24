<?php

namespace MadeSimple\TaskWorker;

use Psr\SimpleCache\CacheInterface;

trait HasCacheTrait
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
}