<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Database;
use Core\LeaderboardSnapshot;
use PDOException;

class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // Web 前台: 读取服务器状态缓存（本地缓存 + 外部 API 拉取）
    public function getServerStatus(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $file = CACHE_PATH . '/server_status.json';
        $redis = Database::redis();
        $cacheKey = 'mc_server_status';
        $lockKey = 'mc_server_status_lock';
        $lockToken = bin2hex(random_bytes(16));
        $lockTtl = 10;

        $readFileFallback = function () use ($file): ?array {
            if (!is_file($file)) {
                return null;
            }
            try {
                $raw = (string)file_get_contents($file);
            } catch (\Throwable $e) {
                return null;
            }
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        };

        if ($redis !== null) {
            try {
                $rawRedis = $redis->get($cacheKey);
                if (is_string($rawRedis) && $rawRedis !== '') {
                    $cachedData = json_decode($rawRedis, true);
                    if (is_array($cachedData)) {
                        $this->json($cachedData);
                        return;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $lockAcquired = false;
        if ($redis !== null) {
            try {
                $lockAcquired = (bool)$redis->set($lockKey, $lockToken, ['nx', 'ex' => $lockTtl]);
            } catch (\Throwable $e) {
                $lockAcquired = false;
            }
        }

        if (!$lockAcquired) {
            $fallbackData = $readFileFallback();
            if (is_array($fallbackData)) {
                $this->json($fallbackData);
                return;
            }
            $this->json([
                'online' => false,
                'players' => ['online' => 0, 'max' => 0, 'list' => []],
            ]);
            return;
        }

        try {
            try {
                $data = $this->fetchFromExternalApi();
            } catch (\Throwable $e) {
                $data = null;
            }

            if (is_array($data)) {
                $normalizedJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($normalizedJson)) {
                    if ($redis !== null) {
                        try {
                            $redis->setex($cacheKey, 60, $normalizedJson);
                        } catch (\Throwable $e) {
                        }
                    }
                    try {
                        file_put_contents($file, $normalizedJson, LOCK_EX);
                    } catch (\Throwable $e) {
                    }
                }
                $this->json($data);
                return;
            }

            $fallbackData = $readFileFallback();
            if (is_array($fallbackData)) {
                $this->json($fallbackData);
                return;
            }

            $this->json([
                'online' => false,
                'players' => ['online' => 0, 'max' => 0, 'list' => []],
            ]);
            return;
        } finally {
            if ($redis !== null) {
                try {
                    if ($redis->get($lockKey) === $lockToken) {
                        $redis->del($lockKey);
                    }
                } catch (\Throwable $e) {
                }
            }
        }
    }

    /**
     * 模组/服务端回调：校验 SERVER_TOKEN 后写入缓存目录（不影响 AuthMe 直连库）。
     */
    public function updateServerStatus(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Content-Type: application/json; charset=utf-8');
        $raw = file_get_contents('php://input');
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $this->json(['success' => false, 'message' => 'Invalid JSON body'], 400);
            return;
        }

        $token = (string)($data['token'] ?? '');
        if ($token === '' || !hash_equals((string)SERVER_TOKEN, $token)) {
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        unset($data['token']);
        try {
            $path = CACHE_PATH . '/mod_api_push.json';
            file_put_contents(
                $path,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Write failed'], 500);
            return;
        }

        $this->json(['success' => true, 'message' => 'Accepted']);
    }

    /**
     * 排行榜模糊搜索：Levenshtein 相似度加权排序（数据源 player_stats）。
     */
    public function searchLeaderboard(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Content-Type: application/json; charset=utf-8');

        $q = trim((string)($_GET['q'] ?? ''));
        $boardKey = trim((string)($_GET['board'] ?? 'play_time'));
        if (mb_strlen($q) > 64) {
            $q = mb_substr($q, 0, 64);
        }

        $config = null;
        foreach (LeaderboardSnapshot::boardConfigs() as $c) {
            if (($c['key'] ?? '') === $boardKey) {
                $config = $c;
                break;
            }
        }

        if ($config === null) {
            $this->json(['success' => false, 'message' => 'Unknown board'], 400);
            return;
        }

        $column = $config['column'];
        $format = $config['format'];
        $unit = $config['unit'];

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT mc_uuid, username, {$column} AS metric FROM player_stats ORDER BY {$column} DESC LIMIT 2000"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            $this->json(['success' => false, 'message' => 'Database error', 'results' => []], 500);
            return;
        }

        $entries = [];
        $rank = 0;
        foreach ($rows as $r) {
            $rank++;
            $raw = (int)($r['metric'] ?? 0);
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
                'rank' => $rank,
            ];
        }

        if ($q === '') {
            $this->json([
                'success' => true,
                'board' => $boardKey,
                'results' => array_slice($entries, 0, 10),
            ]);
            return;
        }

        $qLower = strtolower($q);
        $matched = [];

        foreach ($entries as $entry) {
            $name = (string)($entry['username'] ?? '');
            if ($name === '') {
                continue;
            }
            $nameLower = strtolower($name);
            if (!self::leaderboardSearchNameMatches($qLower, $nameLower)) {
                continue;
            }
            $matched[] = $entry;
        }

        usort(
            $matched,
            static function (array $a, array $b): int {
                return ($a['rank'] <=> $b['rank']);
            }
        );

        $out = array_slice($matched, 0, 25);

        $this->json([
            'success' => true,
            'board' => $boardKey,
            'results' => $out,
        ]);
    }

    /**
     * 匹配规则：游戏名包含关键词（不区分大小写），或整体名称与关键词的 Levenshtein 距离很小。
     * 避免旧逻辑「dist 恰好等于 8 时仍放行」导致与搜索词完全无关的玩家混入结果。
     */
    private static function leaderboardSearchNameMatches(string $qLower, string $nameLower): bool
    {
        $needleLen = strlen($qLower);
        $nameLen = strlen($nameLower);
        if ($needleLen === 0 || $nameLen === 0) {
            return false;
        }
        if ($needleLen > 255 || $nameLen > 255) {
            return false;
        }

        if (str_contains($nameLower, $qLower)) {
            return true;
        }

        $dist = levenshtein($qLower, $nameLower);
        $maxLen = max($needleLen, $nameLen, 1);
        $similarity = 1.0 - ($dist / $maxLen);

        if ($dist <= 2) {
            return true;
        }

        return $dist <= 4 && $similarity >= 0.55;
    }

    private function fetchFromExternalApi(): ?array
    {
        $url = 'https://api.mcstatus.io/v2/status/java/mc.stellarvan.cn:11051';

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['online'] ?? false) === true) {
            $file = CACHE_PATH . '/server_status.json';
            $normalizedJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($normalizedJson)) {
                try {
                    file_put_contents($file, $normalizedJson, LOCK_EX);
                } catch (\Throwable $e) {
                }

                $redis = Database::redis();
                if ($redis !== null) {
                    try {
                        $redis->setex('mc_server_status', 60, $normalizedJson);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 图片跨域反向代理（CORS Image Proxy）
     * 用于第三方皮肤站点不返回 CORS 头时，保证 skinview3d 能读取纹理。
     */
    public function proxySkin(): void
    {
        $rawUrl = trim((string)($_GET['url'] ?? ''));
        if ($rawUrl === '' || preg_match('#^https?://#i', $rawUrl) !== 1) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Bad Request';
            return;
        }

        // 1x1 透明 PNG（作为失败兜底，避免前端白屏）
        $transparentPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X7G8AAAAASUVORK5CYII=';
        $transparentPng = base64_decode($transparentPngBase64, true);

        $imgBytes = null;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($rawUrl);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; skin-proxy/1.0)',
                ]);
                $resp = curl_exec($ch);
                $imgBytes = ($resp !== false && is_string($resp) && $resp !== '') ? $resp : null;
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $resp = @file_get_contents($rawUrl, false, $context);
            $imgBytes = ($resp !== false && is_string($resp) && $resp !== '') ? $resp : null;
        }

        if ($imgBytes === null || $httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300)) {
            $imgBytes = is_string($transparentPng) ? $transparentPng : '';
        }

        header('Content-Type: image/png');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400');
        echo $imgBytes;
        exit;
    }
}
