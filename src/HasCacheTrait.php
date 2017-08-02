<?php

namespace MadeSimple\TaskWorker;

use Psr\SimpleCache\CacheInterface;

/**
 * Class HasCacheTrait
 *
 * @package MadeSimple\TaskWorker
 * @author  Peter Scopes
 */
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