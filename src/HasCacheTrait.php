<?php

namespace MadeSimple\TaskWorker;

use Psr\SimpleCache\CacheInterface;

trait HasCacheTrait
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
}