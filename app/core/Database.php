<?php
declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    /** @var object|null Redis 实例（仅当 phpredis 扩展可用时） */
    private static $redis = null;

    /**
     * 获取 Redis 单例；扩展未启用、配置关闭或连接失败时返回 null（调用方需降级）。
     *
     * @return \Redis|null
     */
    public static function redis()
    {
        if (!REDIS_ENABLED || !extension_loaded('redis')) {
            return null;
        }

        if (self::$redis instanceof \Redis) {
            return self::$redis;
        }

        try {
            $client = new \Redis();
            $connected = $client->connect(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
            if ($connected !== true) {
                return null;
            }
            if (REDIS_PASSWORD !== '') {
                $client->auth(REDIS_PASSWORD);
            }
            if (REDIS_DB !== 0) {
                $client->select(REDIS_DB);
            }
            self::$redis = $client;
            return $client;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                die('数据库连接失败: ' . $e->getMessage());
            }
            http_response_code(500);
            die('数据库连接失败，请联系管理员。');
        }

        return self::$pdo;
    }
}

