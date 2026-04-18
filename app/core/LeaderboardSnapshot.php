<?php
declare(strict_types=1);

namespace Core;

use PDOException;

/**
 * 排行榜聚合快照：优先 Redis，其次文件，最后回源 MySQL；供页面与搜索 API 共用。
 */
final class LeaderboardSnapshot
{
    public const REDIS_KEY = 'mc:leaderboard_snapshot:v1';

    public const FILE_NAME = 'leaderboard_snapshot.json';

    public const TTL_SECONDS = 120;

    /**
     * @return list<array{key:string,column:string,title:string,unit:string,format:string}>
     */
    public static function boardConfigs(): array
    {
        return [
            ['key' => 'play_time', 'column' => 'play_time_ticks', 'title' => '肝帝榜 / 活跃', 'unit' => 'h', 'format' => 'float1'],
            ['key' => 'fly_distance', 'column' => 'fly_distance_cm', 'title' => '飞行榜', 'unit' => 'km', 'format' => 'float2'],
            ['key' => 'blocks_mined', 'column' => 'blocks_mined', 'title' => '矿工榜 / 挖掘', 'unit' => '', 'format' => 'int'],
            ['key' => 'blocks_placed', 'column' => 'blocks_placed', 'title' => '建造榜 / 放置', 'unit' => '', 'format' => 'int'],
            ['key' => 'deaths', 'column' => 'deaths', 'title' => '死神榜 / 死亡', 'unit' => '次', 'format' => 'int'],
            ['key' => 'player_kills', 'column' => 'player_kills', 'title' => '杀手榜 / 击杀', 'unit' => '次', 'format' => 'int'],
            ['key' => 'fish_caught', 'column' => 'fish_caught', 'title' => '渔夫榜 / 钓鱼', 'unit' => '次', 'format' => 'int'],
        ];
    }

    /**
     * @return array{leaderboards: list<array<string,mixed>>, lastUpdate: ?string, leaderboardError: ?string}
     */
    public static function getOrBuild(): array
    {
        $redis = Database::redis();
        if ($redis !== null) {
            try {
                $raw = $redis->get(self::REDIS_KEY);
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && isset($decoded['leaderboards']) && is_array($decoded['leaderboards'])) {
                        return [
                            'leaderboards' => $decoded['leaderboards'],
                            'lastUpdate' => isset($decoded['lastUpdate']) ? (is_string($decoded['lastUpdate']) ? $decoded['lastUpdate'] : null) : null,
                            'leaderboardError' => isset($decoded['leaderboardError']) ? (is_string($decoded['leaderboardError']) ? $decoded['leaderboardError'] : null) : null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // 降级
            }
        }

        $file = CACHE_PATH . DIRECTORY_SEPARATOR . self::FILE_NAME;
        if (is_file($file)) {
            try {
                $mtime = filemtime($file);
                if ($mtime !== false && (time() - $mtime) < self::TTL_SECONDS) {
                    $raw = (string)file_get_contents($file);
                    if ($raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded) && isset($decoded['leaderboards']) && is_array($decoded['leaderboards'])) {
                            return [
                                'leaderboards' => $decoded['leaderboards'],
                                'lastUpdate' => isset($decoded['lastUpdate']) ? (is_string($decoded['lastUpdate']) ? $decoded['lastUpdate'] : null) : null,
                                'leaderboardError' => isset($decoded['leaderboardError']) ? (is_string($decoded['leaderboardError']) ? $decoded['leaderboardError'] : null) : null,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $built = self::buildFromDatabase();
        $payload = json_encode($built, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            if ($redis !== null) {
                try {
                    $redis->setex(self::REDIS_KEY, self::TTL_SECONDS, $payload);
                } catch (\Throwable $e) {
                }
            }
            try {
                file_put_contents($file, $payload, LOCK_EX);
            } catch (\Throwable $e) {
            }
        }

        return $built;
    }

    /**
     * @return array{leaderboards: list<array<string,mixed>>, lastUpdate: ?string, leaderboardError: ?string}
     */
    public static function buildFromDatabase(): array
    {
        $leaderboards = [];
        $leaderboardError = null;
        $lastUpdate = null;

        try {
            $db = Database::connection();
            $stmt = $db->query('SELECT MAX(last_updated) AS last_update FROM player_stats');
            $row = $stmt->fetch();
            $lastUpdate = isset($row['last_update']) && $row['last_update'] !== null ? (string)$row['last_update'] : null;

            foreach (self::boardConfigs() as $config) {
                $column = $config['column'];
                $format = $config['format'];
                $unit = $config['unit'];

                $q = $db->prepare(
                    "SELECT mc_uuid, username, {$column} FROM player_stats ORDER BY {$column} DESC LIMIT 10"
                );
                $q->execute();
                $rows = $q->fetchAll() ?: [];

                $entries = [];
                foreach ($rows as $r) {
                    $raw = (int)($r[$column] ?? 0);
                    $display = $raw;

                    if ($format === 'float1') {
                        $display = $raw <= 0 ? 0.0 : round($raw / 72000, 1);
                    } elseif ($format === 'float2') {
                        $display = $raw <= 0 ? 0.0 : round($raw / 100000, 2);
                    }

                    $entries[] = [
                        'mc_uuid' => (string)($r['mc_uuid'] ?? ''),
                        'username' => (string)($r['username'] ?? ''),
                        'value' => $display,
                        'unit' => $unit,
                    ];
                }

                $leaderboards[] = [
                    'key' => $config['key'],
                    'title' => $config['title'],
                    'unit' => $unit,
                    'format' => $format,
                    'entries' => $entries,
                ];
            }
        } catch (PDOException $e) {
            $leaderboardError = $e->getMessage();
        }

        return [
            'leaderboards' => $leaderboards,
            'lastUpdate' => $lastUpdate,
            'leaderboardError' => $leaderboardError,
        ];
    }
}
