<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\ApiCode;
use Core\ApiResponse;
use Core\Database;
use Core\LeaderboardSnapshot;
use PDOException;

class ApiController extends Controller
{
    private const AVATAR_CACHE_TTL_SECONDS = 21600;
    private const AVATAR_REDIS_TTL_SECONDS = 1800;

    private static bool $avatarCacheTableReady = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function avatar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $rawUsername = trim((string)($_GET['username'] ?? ''));
        $username = preg_match('/^[a-zA-Z0-9_]{1,32}$/', $rawUsername) === 1 ? $rawUsername : 'MHF_Steve';

        $size = (int)($_GET['size'] ?? 32);
        if ($size < 8 || $size > 256) {
            $size = 32;
        }

        $redisKey = 'avatar:v1:' . strtolower($username) . ':' . $size;
        $redis = Database::redis();
        if ($redis !== null) {
            try {
                $cachedBytes = $redis->get($redisKey);
                if (is_string($cachedBytes) && $cachedBytes !== '' && $this->isPngBytes($cachedBytes)) {
                    $this->outputAvatarPng($cachedBytes, self::AVATAR_REDIS_TTL_SECONDS, 'redis');
                    return;
                }
            } catch (\Throwable $e) {
            }
        }

        $cacheDir = CACHE_PATH . '/avatars';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $db = $this->openAvatarPdo();
        $staleBytes = null;
        if ($db instanceof \PDO) {
            try {
                $this->ensureAvatarCacheTable($db);

                $stmt = $db->prepare('
                    SELECT content_hash, storage_rel_path, fetched_at
                    FROM avatar_cache
                    WHERE username = :username AND size_px = :size
                    LIMIT 1
                ');
                $stmt->execute([
                    ':username' => $username,
                    ':size' => $size,
                ]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $storageRelPath = trim((string)($row['storage_rel_path'] ?? ''));
                    $storageAbsPath = $this->resolveAvatarStoragePath($storageRelPath);
                    $fileBytes = $storageAbsPath !== null ? $this->readAvatarFileBytes($storageAbsPath) : null;
                    if (is_string($fileBytes) && $fileBytes !== '' && $this->isPngBytes($fileBytes)) {
                        $fetchedTs = strtotime((string)($row['fetched_at'] ?? ''));
                        $isFresh = ($fetchedTs !== false) && ($fetchedTs >= (time() - self::AVATAR_CACHE_TTL_SECONDS));
                        if ($isFresh) {
                            $this->writeAvatarRedisCache($redis, $redisKey, $fileBytes, self::AVATAR_REDIS_TTL_SECONDS);
                            $this->outputAvatarPng($fileBytes, self::AVATAR_REDIS_TTL_SECONDS, 'file-fresh');
                            return;
                        }
                        $staleBytes = $fileBytes;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $sourceUrl = 'https://minotar.net/helm/' . rawurlencode($username) . '/' . $size . '.png';
        [$remoteBytes, $httpCode] = $this->downloadAvatarPng($sourceUrl);
        if (is_string($remoteBytes) && $remoteBytes !== '' && $this->isPngBytes($remoteBytes)) {
            $hash = hash('sha256', $remoteBytes);
            $storageRelPath = 'storage/cache/avatars/' . $hash . '.png';
            $storageAbsPath = BASE_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $storageRelPath);
            $storageDir = dirname($storageAbsPath);
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0775, true);
            }
            if (!is_file($storageAbsPath)) {
                @file_put_contents($storageAbsPath, $remoteBytes, LOCK_EX);
            }

            if ($db instanceof \PDO) {
                try {
                    $stmt = $db->prepare('
                        INSERT INTO avatar_cache (username, size_px, source_url, content_hash, storage_rel_path, mime, http_code, fetched_at, updated_at)
                        VALUES (:username, :size_px, :source_url, :content_hash, :storage_rel_path, :mime, :http_code, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            source_url = VALUES(source_url),
                            content_hash = VALUES(content_hash),
                            storage_rel_path = VALUES(storage_rel_path),
                            mime = VALUES(mime),
                            http_code = VALUES(http_code),
                            fetched_at = NOW(),
                            updated_at = NOW()
                    ');
                    $stmt->execute([
                        ':username' => $username,
                        ':size_px' => $size,
                        ':source_url' => $sourceUrl,
                        ':content_hash' => $hash,
                        ':storage_rel_path' => $storageRelPath,
                        ':mime' => 'image/png',
                        ':http_code' => $httpCode > 0 ? $httpCode : 200,
                    ]);
                } catch (\Throwable $e) {
                }
            }

            $this->writeAvatarRedisCache($redis, $redisKey, $remoteBytes, self::AVATAR_REDIS_TTL_SECONDS);
            $this->outputAvatarPng($remoteBytes, self::AVATAR_REDIS_TTL_SECONDS, 'remote');
            return;
        }

        if (is_string($staleBytes) && $staleBytes !== '' && $this->isPngBytes($staleBytes)) {
            $this->writeAvatarRedisCache($redis, $redisKey, $staleBytes, 600);
            $this->outputAvatarPng($staleBytes, 600, 'file-stale');
            return;
        }

        $fallbackBytes = $this->getLocalFallbackAvatarBytes();
        $this->writeAvatarRedisCache($redis, $redisKey, $fallbackBytes, 300);
        $this->outputAvatarPng($fallbackBytes, 300, 'fallback');
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
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Invalid JSON body'], 400);
            return;
        }

        $token = (string)($data['token'] ?? '');
        if ($token === '' || !hash_equals((string)SERVER_TOKEN, $token)) {
            $this->json(['success' => false, 'code' => ApiCode::AUTH_INVALID, 'message' => 'Unauthorized'], 401);
            return;
        }

        unset($data['token']);
        $data = $this->normalizeRealtimePayload($data);
        try {
            $path = CACHE_PATH . '/mod_api_push.json';
            file_put_contents(
                $path,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Write failed'], 500);
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
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Unknown board'], 400);
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
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Database error', 'results' => []], 500);
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

    private function openAvatarPdo(): ?\PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            return new \PDO($dsn, DB_USER, DB_PASS, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function ensureAvatarCacheTable(\PDO $db): void
    {
        if (self::$avatarCacheTableReady) {
            return;
        }

        $db->exec('
            CREATE TABLE IF NOT EXISTS avatar_cache (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(32) NOT NULL,
                size_px SMALLINT UNSIGNED NOT NULL DEFAULT 32,
                source_url VARCHAR(512) NOT NULL,
                content_hash CHAR(64) NOT NULL,
                storage_rel_path VARCHAR(255) NOT NULL,
                mime VARCHAR(64) NOT NULL DEFAULT \'image/png\',
                http_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
                fetched_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_avatar_cache_username_size (username, size_px),
                INDEX idx_avatar_cache_fetched_at (fetched_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        self::$avatarCacheTableReady = true;
    }

    private function writeAvatarRedisCache($redis, string $key, string $bytes, int $ttl): void
    {
        if ($redis === null || $bytes === '') {
            return;
        }

        try {
            $redis->setex($key, max(60, $ttl), $bytes);
        } catch (\Throwable $e) {
        }
    }

    private function resolveAvatarStoragePath(string $storageRelPath): ?string
    {
        $normalized = str_replace('\\', '/', trim($storageRelPath));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }
        if (!str_starts_with($normalized, 'storage/cache/avatars/')) {
            return null;
        }
        return BASE_PATH . '/' . $normalized;
    }

    private function readAvatarFileBytes(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        try {
            $bytes = file_get_contents($path);
        } catch (\Throwable $e) {
            return null;
        }

        return (is_string($bytes) && $bytes !== '') ? $bytes : null;
    }

    /**
     * @return array{0:?string,1:int}
     */
    private function downloadAvatarPng(string $url): array
    {
        $httpCode = 0;
        $imgBytes = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; avatar-proxy/1.0)',
                ]);
                $resp = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($resp !== false && is_string($resp) && $resp !== '') {
                    $imgBytes = $resp;
                }
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $resp = @file_get_contents($url, false, $context);
            if ($resp !== false && is_string($resp) && $resp !== '') {
                $imgBytes = $resp;
            }
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string)$headerLine, $m) === 1) {
                        $httpCode = (int)$m[1];
                        break;
                    }
                }
            }
        }

        if ($httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300)) {
            return [null, $httpCode];
        }
        if (!is_string($imgBytes) || $imgBytes === '' || !$this->isPngBytes($imgBytes)) {
            return [null, $httpCode];
        }

        return [$imgBytes, $httpCode];
    }

    private function isPngBytes(string $bytes): bool
    {
        return strlen($bytes) >= 8 && substr($bytes, 0, 8) === "\x89PNG\x0d\x0a\x1a\x0a";
    }

    private function getLocalFallbackAvatarBytes(): string
    {
        $fallbackPath = PUBLIC_PATH . '/images/owner_avatar.png';
        if (is_file($fallbackPath)) {
            try {
                $bytes = file_get_contents($fallbackPath);
                if (is_string($bytes) && $bytes !== '' && $this->isPngBytes($bytes)) {
                    return $bytes;
                }
            } catch (\Throwable $e) {
            }
        }

        $transparentPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X7G8AAAAASUVORK5CYII=';
        $transparentPng = base64_decode($transparentPngBase64, true);
        return is_string($transparentPng) ? $transparentPng : '';
    }

    private function outputAvatarPng(string $bytes, int $maxAge, string $source): void
    {
        ApiResponse::ensureRequestIdHeader();
        header_remove('Pragma');
        header_remove('Expires');
        header('Content-Type: image/png');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=' . max(60, $maxAge));
        if ($source !== '') {
            header('X-Avatar-Cache: ' . $source);
        }
        echo $bytes;
        exit;
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

    private function normalizeRealtimePayload(array $payload): array
    {
        $normalized = $payload;

        if (!isset($normalized['server']) || !is_array($normalized['server'])) {
            $normalized['server'] = [];
        }
        if (!isset($normalized['stats']) || !is_array($normalized['stats'])) {
            $normalized['stats'] = [];
        }
        if (!isset($normalized['players']) || !is_array($normalized['players'])) {
            $normalized['players'] = [];
        }
        if (!isset($normalized['plugins']) || !is_array($normalized['plugins'])) {
            $normalized['plugins'] = [];
        }
        if (!isset($normalized['chat']) || !is_array($normalized['chat'])) {
            $normalized['chat'] = [];
        }

        return $normalized;
    }

    /**
     * 图片跨域反向代理（CORS Image Proxy）
     * 用于第三方皮肤站点不返回 CORS 头时，保证 skinview3d 能读取纹理。
     */
    public function proxySkin(): void
    {
        ApiResponse::ensureRequestIdHeader();
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
