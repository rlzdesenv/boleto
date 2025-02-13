<?php

namespace Boleto\Factory;

use Cache\Adapter\Apcu\ApcuCachePool;

class CacheFactory
{
    private static ?ApcuCachePool $cache = null;

    public static function getCache(): ApcuCachePool
    {
        if (self::$cache === null) {
            self::$cache = new ApcuCachePool();
        }

        return self::$cache;
    }
}