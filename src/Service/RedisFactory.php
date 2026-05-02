<?php declare(strict_types=1);

namespace Danilovl\MenuBuilderBundle\Service;

use InvalidArgumentException;
use LogicException;
use Redis;

class RedisFactory
{
    public static function create(?string $dsn): Redis
    {
        if ($dsn === null || $dsn === '') {
            throw new LogicException(
                'danilovl_menu_builder.storage.driver=redis requires storage.redis_dsn to be set.'
            );
        }

        $parts = parse_url($dsn);
        if (!is_array($parts) || !isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid Redis DSN: ' . $dsn);
        }

        $port = (int) ($parts['port'] ?? 6_379);
        $redis = new Redis;
        $redis->connect($parts['host'], $port);

        if (isset($parts['pass'])) {
            $redis->auth($parts['pass']);
        }

        if (isset($parts['path'])) {
            $db = (int) mb_ltrim($parts['path'], '/');
            if ($db > 0) {
                $redis->select($db);
            }
        }

        return $redis;
    }
}
