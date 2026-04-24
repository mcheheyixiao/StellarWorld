<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\ApiCode;
use Core\ApiResponse;
use Core\Database;
use Core\LeaderboardSnapshot;
use PDOException;

/**
 * NOTE: SQL executed via PDO; IDE SqlNoDataSourceInspection warnings can be ignored.
 * @noinspection SqlNoDataSourceInspection
 */
class ApiController extends Controller
{
    private const AVATAR_CACHE_TTL_SECONDS = 21600;
    private const AVATAR_REDIS_TTL_SECONDS = 1800;
    private const STATUS_CACHE_TTL_SECONDS = 1;
    private const HEALTH_CACHE_TTL_SECONDS = 1;
    private const PLAYERS_CACHE_TTL_SECONDS = 2;
    private const CHAT_CACHE_TTL_SECONDS = 1;
    private const SNAPSHOT_FALLBACK_MAX_AGE_SECONDS = 3600;

    private static bool $avatarCacheTableReady = false;
    private static bool $serverStatusHistoryTableReady = false;

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

        header('Cache-Control: private, max-age=' . self::STATUS_CACHE_TTL_SECONDS);

        $cached = $this->loadEndpointCache('status', self::STATUS_CACHE_TTL_SECONDS);
        if (is_array($cached)) {
            $normalizedCached = $this->normalizeStatusPayload($cached);
            if (is_array($normalizedCached)) {
                $this->json($normalizedCached);
                return;
            }
        }

        $status = $this->loadStatusFromWsApi();
        if (is_array($status)) {
            $this->persistStatusHistory($status, 'ws-api');
            $this->saveEndpointCache('status', $status);
            $this->saveSnapshotCache('status', $status);
            $this->saveSnapshotCache('players', $this->extractPlayersPayload($status));
            $this->saveSnapshotCache('chat', $this->extractChatPayload($status));
            $this->json($status);
            return;
        }

        $fallback = $this->loadLatestStatusFromDatabase();
        if (is_array($fallback)) {
            $this->json($fallback);
            return;
        }

        $snapshot = $this->loadSnapshotCache('status', self::SNAPSHOT_FALLBACK_MAX_AGE_SECONDS);
        if (is_array($snapshot)) {
            $normalizedSnapshot = $this->normalizeStatusPayload($snapshot);
            if (is_array($normalizedSnapshot)) {
                $this->json($normalizedSnapshot);
                return;
            }
        }

        $this->json($this->defaultOfflineStatus());
    }

    public function getPlayers(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Cache-Control: private, max-age=' . self::PLAYERS_CACHE_TTL_SECONDS);

        $cached = $this->loadEndpointCache('players', self::PLAYERS_CACHE_TTL_SECONDS);
        if (is_array($cached)) {
            $this->json($this->sanitizePlayersPayload($cached));
            return;
        }

        $players = $this->loadPlayersFromWsApi();
        if (is_array($players)) {
            $this->saveEndpointCache('players', $players);
            $this->saveSnapshotCache('players', $players);
            $this->json($players);
            return;
        }

        $cachedStatus = $this->loadEndpointCache('status', self::STATUS_CACHE_TTL_SECONDS);
        if (is_array($cachedStatus)) {
            $normalizedCachedStatus = $this->normalizeStatusPayload($cachedStatus);
            if (is_array($normalizedCachedStatus)) {
                $this->json($this->extractPlayersPayload($normalizedCachedStatus));
                return;
            }
        }

        $status = $this->loadStatusFromWsApi();
        if (is_array($status)) {
            $this->persistStatusHistory($status, 'ws-api');
            $playersPayload = $this->extractPlayersPayload($status);
            $this->saveEndpointCache('status', $status);
            $this->saveSnapshotCache('status', $status);
            $this->saveEndpointCache('players', $playersPayload);
            $this->saveSnapshotCache('players', $playersPayload);
            $this->saveSnapshotCache('chat', $this->extractChatPayload($status));
            $this->json($playersPayload);
            return;
        }

        $fallback = $this->loadLatestStatusFromDatabase();
        if (is_array($fallback)) {
            $this->json($this->extractPlayersPayload($fallback));
            return;
        }

        $playersSnapshot = $this->loadSnapshotCache('players', self::SNAPSHOT_FALLBACK_MAX_AGE_SECONDS);
        if (is_array($playersSnapshot)) {
            $this->json($this->sanitizePlayersPayload($playersSnapshot));
            return;
        }

        $statusSnapshot = $this->loadSnapshotCache('status', self::SNAPSHOT_FALLBACK_MAX_AGE_SECONDS);
        if (is_array($statusSnapshot)) {
            $normalizedStatusSnapshot = $this->normalizeStatusPayload($statusSnapshot);
            if (is_array($normalizedStatusSnapshot)) {
                $this->json($this->extractPlayersPayload($normalizedStatusSnapshot));
                return;
            }
        }

        $this->json($this->extractPlayersPayload($this->defaultOfflineStatus()));
    }

    public function getChat(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Cache-Control: private, max-age=' . self::CHAT_CACHE_TTL_SECONDS);

        $cached = $this->loadEndpointCache('chat', self::CHAT_CACHE_TTL_SECONDS);
        if (is_array($cached)) {
            $this->json($this->sanitizeChatPayload($cached));
            return;
        }

        $chat = $this->loadChatFromWsApi();
        if (is_array($chat)) {
            $this->saveEndpointCache('chat', $chat);
            $this->saveSnapshotCache('chat', $chat);
            $this->json($chat);
            return;
        }

        $fallbackStatus = $this->loadLatestStatusFromDatabase();
        if (is_array($fallbackStatus)) {
            $chatFromDb = $this->extractChatPayload($fallbackStatus);
            if ($chatFromDb !== []) {
                $this->json($chatFromDb);
                return;
            }
        }

        $chatSnapshot = $this->loadSnapshotCache('chat', self::SNAPSHOT_FALLBACK_MAX_AGE_SECONDS);
        if (is_array($chatSnapshot)) {
            $this->json($this->sanitizeChatPayload($chatSnapshot));
            return;
        }

        $statusSnapshot = $this->loadSnapshotCache('status', self::SNAPSHOT_FALLBACK_MAX_AGE_SECONDS);
        if (is_array($statusSnapshot)) {
            $this->json($this->extractChatPayload($statusSnapshot));
            return;
        }

        $this->json([]);
    }

    public function getRealtimeHealth(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Cache-Control: private, max-age=' . self::HEALTH_CACHE_TTL_SECONDS);

        $cached = $this->loadEndpointCache('health', self::HEALTH_CACHE_TTL_SECONDS);
        if (is_array($cached)) {
            $this->json($this->normalizeRealtimeHealthPayload($cached, true));
            return;
        }

        $health = $this->loadHealthFromWsApi();
        if (is_array($health)) {
            $this->saveEndpointCache('health', $health);
            $this->json($health);
            return;
        }

        $fallback = [
            'realtime_api' => 'ERROR',
            'plugin' => 'Offline',
            'last_update_seconds' => $this->inferFallbackLastUpdateSeconds(),
            'players_source' => 'Fallback',
            'fallback_enabled' => true,
            'fallback' => 'Enabled',
            'checked_at' => time(),
        ];
        $this->saveEndpointCache('health', $fallback);
        $this->json($fallback);
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
        $normalized = $this->normalizeRealtimePayload($data);
        $status = $this->normalizeStatusPayload($normalized);
        if (!is_array($status)) {
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Invalid status payload'], 400);
            return;
        }

        if (!$this->persistStatusHistory($status, 'push-api')) {
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Persist failed'], 500);
            return;
        }

        $this->saveSnapshotCache('status', $status);
        $this->saveSnapshotCache('players', $this->extractPlayersPayload($status));
        $this->saveSnapshotCache('chat', $this->extractChatPayload($normalized));

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

    private function loadStatusFromWsApi(): ?array
    {
        $payload = $this->fetchWsApiJson('/api/status');
        if (!is_array($payload)) {
            return null;
        }

        return $this->normalizeStatusPayload($payload);
    }

    private function loadPlayersFromWsApi(): ?array
    {
        $payload = $this->fetchWsApiJson('/api/players');
        if (!is_array($payload)) {
            return null;
        }

        $candidate = $this->unwrapApiPayload($payload);
        if (isset($candidate['players']) && is_array($candidate['players']) && $this->isAssocArray($candidate['players'])) {
            return $this->sanitizePlayersPayload($candidate['players']);
        }

        if (!$this->isAssocArray($candidate)) {
            return $this->sanitizePlayersPayload(['list' => $candidate]);
        }

        if (array_key_exists('list', $candidate) || array_key_exists('online', $candidate) || array_key_exists('max', $candidate)) {
            return $this->sanitizePlayersPayload($candidate);
        }

        $status = $this->normalizeStatusPayload($payload);
        return is_array($status) ? $this->extractPlayersPayload($status) : null;
    }

    private function loadChatFromWsApi(): ?array
    {
        $payload = $this->fetchWsApiJson('/api/chat');
        if (!is_array($payload)) {
            return null;
        }

        $candidate = $this->unwrapApiPayload($payload);
        if (isset($candidate['chat']) && is_array($candidate['chat'])) {
            return $this->sanitizeChatPayload($candidate['chat']);
        }

        if (isset($candidate['messages']) && is_array($candidate['messages'])) {
            return $this->sanitizeChatPayload($candidate['messages']);
        }

        if (isset($candidate['list']) && is_array($candidate['list'])) {
            return $this->sanitizeChatPayload($candidate['list']);
        }

        if (!$this->isAssocArray($candidate)) {
            return $this->sanitizeChatPayload($candidate);
        }

        if (array_key_exists('message', $candidate) || array_key_exists('content', $candidate) || array_key_exists('text', $candidate)) {
            return $this->sanitizeChatPayload([$candidate]);
        }

        $statusPayload = $this->normalizeStatusPayload($payload);
        if (is_array($statusPayload)) {
            return $this->extractChatPayload($statusPayload);
        }

        return null;
    }

    private function loadHealthFromWsApi(): ?array
    {
        $payload = $this->fetchWsApiJson('/health');
        if (!is_array($payload)) {
            return null;
        }

        return $this->normalizeRealtimeHealthPayload($payload, true);
    }

    private function normalizeRealtimeHealthPayload(array $payload, bool $requestOk): array
    {
        $candidate = $this->unwrapApiPayload($payload);
        $checkedAt = time();

        $apiOk = $this->extractHealthApiOk($candidate, $requestOk);
        $fallbackEnabled = $this->extractHealthFallbackEnabled($candidate, !$apiOk);
        $pluginOnline = $this->extractHealthPluginOnline($candidate, $apiOk);
        $playersSource = $this->normalizePlayersSource(
            $this->firstAvailableValue($candidate, ['players_source', 'playersSource', 'source', 'source_type']),
            $fallbackEnabled
        );
        $lastUpdateSeconds = $this->resolveLastUpdateSeconds($candidate, $checkedAt);

        return [
            'realtime_api' => $apiOk ? 'OK' : 'ERROR',
            'plugin' => $pluginOnline ? 'Online' : 'Offline',
            'last_update_seconds' => $lastUpdateSeconds,
            'players_source' => $playersSource,
            'fallback_enabled' => $fallbackEnabled,
            'fallback' => $fallbackEnabled ? 'Enabled' : 'Disabled',
            'checked_at' => $checkedAt,
        ];
    }

    private function extractHealthApiOk(array $candidate, bool $default): bool
    {
        $apiState = $this->firstAvailableValue(
            $candidate,
            ['realtime_api', 'api', 'api_status', 'status', 'state', 'ok', 'healthy']
        );
        $resolved = $this->parseHealthBool($apiState);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function extractHealthPluginOnline(array $candidate, bool $default): bool
    {
        $pluginValue = $this->firstAvailableValue(
            $candidate,
            ['plugin_online', 'pluginOnline', 'plugin_status', 'plugin', 'plugins']
        );

        if (is_array($pluginValue)) {
            $nested = $this->firstAvailableValue($pluginValue, ['online', 'enabled', 'status', 'state', 'ok', 'healthy']);
            $resolvedNested = $this->parseHealthBool($nested);
            if ($resolvedNested !== null) {
                return $resolvedNested;
            }
        }

        $resolved = $this->parseHealthBool($pluginValue);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function extractHealthFallbackEnabled(array $candidate, bool $default): bool
    {
        $fallbackValue = $this->firstAvailableValue(
            $candidate,
            ['fallback_enabled', 'fallbackEnabled', 'fallback', 'degraded', 'using_fallback']
        );
        $resolved = $this->parseHealthBool($fallbackValue);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function normalizePlayersSource($rawSource, bool $fallbackEnabled): string
    {
        if (is_string($rawSource) && trim($rawSource) !== '') {
            $normalized = strtolower(trim($rawSource));
            if (str_contains($normalized, 'ws')) {
                return 'WS';
            }
            if (str_contains($normalized, 'fallback') || str_contains($normalized, 'cache') || str_contains($normalized, 'db')) {
                return 'Fallback';
            }
        }

        return $fallbackEnabled ? 'Fallback' : 'WS';
    }

    private function resolveLastUpdateSeconds(array $candidate, int $nowTs): ?int
    {
        $secondsValue = $this->firstAvailableValue($candidate, ['last_update_seconds', 'lastUpdateSeconds', 'age_seconds', 'ageSeconds']);
        if (is_numeric($secondsValue)) {
            $seconds = max(0, (int)$secondsValue);
            return $seconds;
        }

        $timestampValue = $this->firstAvailableValue(
            $candidate,
            ['updated_at', 'updatedAt', 'last_update_at', 'lastUpdateAt', 'timestamp', 'time', 'last_update', 'lastUpdate', 'checked_at']
        );
        $timestamp = $this->parseUnixTimestamp($timestampValue);
        if ($timestamp !== null) {
            return max(0, $nowTs - $timestamp);
        }

        return null;
    }

    private function parseUnixTimestamp($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $num = (int)$value;
            if ($num > 1000000000000) {
                return (int)floor($num / 1000);
            }
            return $num > 10000000000 ? (int)floor($num / 1000) : $num;
        }

        if (!is_string($value)) {
            return null;
        }

        $ts = strtotime($value);
        return $ts === false ? null : (int)$ts;
    }

    private function parseHealthBool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int)$value) > 0;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        $stateFromWord = $this->parseHealthStateString($normalized);
        if ($stateFromWord !== null) {
            return $stateFromWord;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        return null;
    }

    private function parseHealthStateString(string $value): ?bool
    {
        if (in_array($value, ['ok', 'online', 'up', 'healthy', 'running', 'active', 'enabled', 'ws'], true)) {
            return true;
        }
        if (in_array($value, ['error', 'offline', 'down', 'unhealthy', 'failed', 'fail', 'inactive', 'disabled'], true)) {
            return false;
        }

        return null;
    }

    private function firstAvailableValue(array $source, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return null;
    }

    private function cacheFileAgeSeconds(string $cacheName): ?int
    {
        $filePath = $this->cacheFilePath($cacheName);
        if ($filePath === null || !is_file($filePath)) {
            return null;
        }

        $mtime = @filemtime($filePath);
        if ($mtime === false) {
            return null;
        }

        return max(0, time() - (int)$mtime);
    }

    private function inferFallbackLastUpdateSeconds(): ?int
    {
        $ages = [];
        foreach (['ws-api-status', 'ws-snapshot-status'] as $cacheName) {
            $age = $this->cacheFileAgeSeconds($cacheName);
            if ($age !== null) {
                $ages[] = $age;
            }
        }

        if ($ages === []) {
            return null;
        }

        return min($ages);
    }

    private function wsStatusApiBaseUrl(): string
    {
        $baseUrl = defined('WS_STATUS_API_BASE') ? trim((string)WS_STATUS_API_BASE) : '';
        if ($baseUrl === '') {
            $baseUrl = 'http://localhost:3001';
        }

        return rtrim($baseUrl, '/');
    }

    private function wsStatusApiTimeoutSeconds(): int
    {
        $timeoutMs = defined('WS_STATUS_API_TIMEOUT_MS') ? (int)WS_STATUS_API_TIMEOUT_MS : 2500;
        return max(1, (int)ceil(max(500, $timeoutMs) / 1000));
    }

    private function fetchWsApiJson(string $path): ?array
    {
        $url = $this->wsStatusApiBaseUrl() . '/' . ltrim($path, '/');
        return $this->fetchJsonFromUrl($url, $this->wsStatusApiTimeoutSeconds());
    }

    private function fetchJsonFromUrl(string $url, int $timeoutSeconds): ?array
    {
        $responseBody = null;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => min(3, max(1, $timeoutSeconds)),
                    CURLOPT_TIMEOUT => $timeoutSeconds,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                    CURLOPT_USERAGENT => 'stellarvan-status-client/1.0',
                ]);
                $resp = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($resp !== false && is_string($resp) && trim($resp) !== '') {
                    $responseBody = $resp;
                }
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeoutSeconds,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\n",
                ],
            ]);
            $resp = @file_get_contents($url, false, $context);
            if ($resp !== false && is_string($resp) && trim($resp) !== '') {
                $responseBody = $resp;
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

        if (!is_string($responseBody) || $responseBody === '') {
            return null;
        }
        if ($httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300)) {
            return null;
        }

        $decoded = json_decode($responseBody, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function unwrapApiPayload(array $payload): array
    {
        $candidate = $payload;
        if (isset($candidate['data']) && is_array($candidate['data'])) {
            $candidate = $candidate['data'];
        }
        if (isset($candidate['payload']) && is_array($candidate['payload'])) {
            $candidate = $candidate['payload'];
        }

        return $candidate;
    }

    private function normalizeStatusPayload(array $payload): ?array
    {
        $candidate = $this->unwrapApiPayload($payload);
        if (isset($candidate['status']) && is_array($candidate['status'])) {
            $candidate = $candidate['status'];
        }

        if (isset($candidate['server']) && is_array($candidate['server'])) {
            return $this->mapRealtimeSnapshotToLegacyStatus($candidate);
        }

        if (array_key_exists('online', $candidate) || isset($candidate['players'])) {
            return $this->sanitizeLegacyStatusPayload($candidate);
        }

        if (!$this->isAssocArray($candidate)) {
            return $this->sanitizeLegacyStatusPayload(['players' => ['list' => $candidate]]);
        }

        if (array_key_exists('list', $candidate) || array_key_exists('online', $candidate) || array_key_exists('max', $candidate)) {
            return $this->sanitizeLegacyStatusPayload(['players' => $candidate]);
        }

        return null;
    }

    private function mapRealtimeSnapshotToLegacyStatus(array $snapshot): array
    {
        $server = isset($snapshot['server']) && is_array($snapshot['server']) ? $snapshot['server'] : [];
        $stats = isset($snapshot['stats']) && is_array($snapshot['stats']) ? $snapshot['stats'] : [];

        $playersNode = $snapshot['players'] ?? [];
        if (is_array($playersNode) && !$this->isAssocArray($playersNode)) {
            $playersNode = ['list' => $playersNode];
        }
        if (!is_array($playersNode)) {
            $playersNode = [];
        }

        $players = $this->sanitizePlayersPayload($playersNode);

        if (isset($stats['onlinePlayers']) && is_numeric($stats['onlinePlayers'])) {
            $players['online'] = max(0, (int)$stats['onlinePlayers']);
        }
        if (isset($stats['maxPlayers']) && is_numeric($stats['maxPlayers'])) {
            $players['max'] = max(0, (int)$stats['maxPlayers']);
        }
        if ($players['online'] === 0 && count($players['list']) > 0) {
            $players['online'] = count($players['list']);
        }

        $status = [
            'online' => array_key_exists('online', $server) ? (bool)$server['online'] : ($players['online'] > 0),
            'players' => $players,
        ];

        if (array_key_exists('motd', $server)) {
            $status['motd'] = $server['motd'];
        } elseif (array_key_exists('motd', $snapshot)) {
            $status['motd'] = $snapshot['motd'];
        }

        if (array_key_exists('version', $server)) {
            $status['version'] = $server['version'];
        } elseif (array_key_exists('version', $snapshot)) {
            $status['version'] = $snapshot['version'];
        }

        if (array_key_exists('icon', $server)) {
            $status['icon'] = $server['icon'];
        } elseif (array_key_exists('favicon', $snapshot)) {
            $status['icon'] = $snapshot['favicon'];
        }

        return $this->sanitizeLegacyStatusPayload($status);
    }

    private function sanitizeLegacyStatusPayload(array $payload): array
    {
        $playersSource = [];
        if (isset($payload['players']) && is_array($payload['players'])) {
            $playersSource = $payload['players'];
        }
        if ($playersSource === [] && isset($payload['sample']) && is_array($payload['sample'])) {
            $playersSource = ['list' => $payload['sample']];
        }

        $players = $this->sanitizePlayersPayload($playersSource);
        $online = array_key_exists('online', $payload) ? (bool)$payload['online'] : ($players['online'] > 0);

        $normalized = $payload;
        $normalized['online'] = $online;
        $normalized['players'] = $players;

        return $normalized;
    }

    private function extractPlayersPayload(array $statusPayload): array
    {
        $normalized = $this->sanitizeLegacyStatusPayload($statusPayload);
        if (isset($normalized['players']) && is_array($normalized['players'])) {
            return $normalized['players'];
        }

        return ['online' => 0, 'max' => 0, 'list' => []];
    }

    private function extractChatPayload(array $statusPayload): array
    {
        $normalized = $this->sanitizeLegacyStatusPayload($statusPayload);
        if (isset($normalized['chat']) && is_array($normalized['chat'])) {
            return $this->sanitizeChatPayload($normalized['chat']);
        }
        if (isset($normalized['messages']) && is_array($normalized['messages'])) {
            return $this->sanitizeChatPayload($normalized['messages']);
        }
        if (isset($normalized['data']['chat']) && is_array($normalized['data']['chat'])) {
            return $this->sanitizeChatPayload($normalized['data']['chat']);
        }

        return [];
    }

    private function sanitizePlayersPayload(array $payload): array
    {
        $playerNode = $payload;
        if (isset($playerNode['players']) && is_array($playerNode['players']) && $this->isAssocArray($playerNode['players'])) {
            $playerNode = $playerNode['players'];
        }
        if (!$this->isAssocArray($playerNode)) {
            $playerNode = ['list' => $playerNode];
        }

        $listSource = [];
        if (isset($playerNode['list']) && is_array($playerNode['list'])) {
            $listSource = $playerNode['list'];
        } elseif (isset($playerNode['sample']) && is_array($playerNode['sample'])) {
            $listSource = $playerNode['sample'];
        } elseif (isset($playerNode['players']) && is_array($playerNode['players']) && !$this->isAssocArray($playerNode['players'])) {
            $listSource = $playerNode['players'];
        }

        $list = $this->normalizePlayersList($listSource);
        $online = isset($playerNode['online']) && is_numeric($playerNode['online'])
            ? max(0, (int)$playerNode['online'])
            : count($list);

        if ($online === 0 && count($list) > 0) {
            $online = count($list);
        }

        $max = 0;
        if (isset($playerNode['max']) && is_numeric($playerNode['max'])) {
            $max = max(0, (int)$playerNode['max']);
        } elseif (isset($playerNode['max']) && is_scalar($playerNode['max'])) {
            $max = (string)$playerNode['max'];
        }

        return [
            'online' => $online,
            'max' => $max,
            'list' => $list,
        ];
    }

    private function normalizePlayersList(array $players): array
    {
        $normalized = [];
        foreach ($players as $player) {
            if (is_string($player)) {
                $name = trim($player);
                if ($name === '') {
                    continue;
                }
                $normalized[] = ['name' => $name, 'name_clean' => $name];
                continue;
            }

            if (!is_array($player)) {
                continue;
            }

            $name = trim((string)($player['name_clean'] ?? $player['name'] ?? $player['username'] ?? ''));
            if ($name === '') {
                continue;
            }

            $entry = $player;
            if (!isset($entry['name']) || !is_string($entry['name']) || trim($entry['name']) === '') {
                $entry['name'] = $name;
            }
            if (!isset($entry['name_clean']) || !is_string($entry['name_clean']) || trim($entry['name_clean']) === '') {
                $entry['name_clean'] = $name;
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    private function sanitizeChatPayload(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $item) {
            if (is_string($item)) {
                $message = trim($item);
                if ($message === '') {
                    continue;
                }

                $normalized[] = [
                    'player' => 'Server',
                    'message' => $message,
                    'time' => time(),
                ];
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $message = trim((string)($item['message'] ?? $item['content'] ?? $item['text'] ?? ''));
            if ($message === '') {
                continue;
            }

            $player = trim((string)($item['player'] ?? $item['playerName'] ?? $item['username'] ?? $item['name'] ?? $item['sender'] ?? ''));
            if ($player === '') {
                $player = 'Server';
            }

            $normalizedItem = $item;
            $normalizedItem['player'] = $player;
            $normalizedItem['message'] = $message;

            $time = $item['time'] ?? $item['timestamp'] ?? $item['created_at'] ?? $item['at'] ?? null;
            if ($time !== null && $time !== '') {
                $normalizedItem['time'] = $time;
            } elseif (!array_key_exists('time', $normalizedItem)) {
                $normalizedItem['time'] = time();
            }

            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    private function isAssocArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function defaultOfflineStatus(): array
    {
        return [
            'online' => false,
            'players' => ['online' => 0, 'max' => 0, 'list' => []],
        ];
    }

    private function persistStatusHistory(array $statusPayload, string $source): bool
    {
        $normalized = $this->sanitizeLegacyStatusPayload($statusPayload);
        $payloadJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson) || $payloadJson === '') {
            return false;
        }

        try {
            $db = Database::connection();
            $this->ensureServerStatusHistoryTable($db);

            $stmt = $db->prepare('
                INSERT INTO server_status_history (source, payload_json, created_at)
                VALUES (:source, :payload_json, NOW())
            ');
            $stmt->execute([
                ':source' => substr(trim($source), 0, 32),
                ':payload_json' => $payloadJson,
            ]);

            $db->exec('
                DELETE FROM server_status_history
                WHERE created_at < (NOW() - INTERVAL 30 DAY)
            ');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loadLatestStatusFromDatabase(): ?array
    {
        try {
            $db = Database::connection();
            $this->ensureServerStatusHistoryTable($db);

            $stmt = $db->query('
                SELECT payload_json
                FROM server_status_history
                ORDER BY id DESC
                LIMIT 1
            ');
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $decoded = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($decoded)) {
                return null;
            }

            return $this->normalizeStatusPayload($decoded);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function saveEndpointCache(string $name, array $payload): void
    {
        $this->writeJsonCacheFile('ws-api-' . $name, $payload);
    }

    private function loadEndpointCache(string $name, int $maxAgeSeconds): ?array
    {
        return $this->readJsonCacheFile('ws-api-' . $name, $maxAgeSeconds);
    }

    private function saveSnapshotCache(string $name, array $payload): void
    {
        $this->writeJsonCacheFile('ws-snapshot-' . $name, $payload);
    }

    private function loadSnapshotCache(string $name, int $maxAgeSeconds): ?array
    {
        return $this->readJsonCacheFile('ws-snapshot-' . $name, $maxAgeSeconds);
    }

    private function writeJsonCacheFile(string $name, array $payload): void
    {
        $filePath = $this->cacheFilePath($name);
        if ($filePath === null) {
            return;
        }

        $envelope = [
            'captured_at' => time(),
            'payload' => $payload,
        ];
        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return;
        }

        try {
            @file_put_contents($filePath, $json, LOCK_EX);
        } catch (\Throwable $e) {
        }
    }

    private function readJsonCacheFile(string $name, int $maxAgeSeconds): ?array
    {
        $filePath = $this->cacheFilePath($name);
        if ($filePath === null || !is_file($filePath)) {
            return null;
        }

        $safeMaxAge = max(1, $maxAgeSeconds);
        $mtime = @filemtime($filePath);
        if ($mtime === false || (time() - (int)$mtime) > $safeMaxAge) {
            return null;
        }

        try {
            $raw = @file_get_contents($filePath);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (isset($decoded['payload']) && is_array($decoded['payload'])) {
            return $decoded['payload'];
        }

        return $decoded;
    }

    private function cacheFilePath(string $name): ?string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower(trim($name)));
        if (!is_string($safeName) || $safeName === '') {
            return null;
        }

        $cacheDir = CACHE_PATH;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        return rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . $safeName . '.json';
    }

    private function ensureServerStatusHistoryTable(\PDO $db): void
    {
        if (self::$serverStatusHistoryTableReady) {
            return;
        }

        $db->exec('
            CREATE TABLE IF NOT EXISTS server_status_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(32) NOT NULL DEFAULT \'ws-api\',
                payload_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_server_status_history_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        self::$serverStatusHistoryTableReady = true;
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
