<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\ApiCode;
use Core\ApiResponse;
use Core\Database;
use Core\LeaderboardSnapshot;
use Model\Checkin;
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
    private const PLUGINS_CACHE_TTL_SECONDS = 1;
    private const PLAYERS_CACHE_TTL_SECONDS = 2;
    private const CHAT_CACHE_TTL_SECONDS = 1;
    private const SNAPSHOT_FALLBACK_MAX_AGE_SECONDS = 3600;

    private static bool $avatarCacheTableReady = false;
    private static bool $serverStatusHistoryTableReady = false;
    private Checkin $checkins;
    private ?array $jsonRequestBody = null;
    private ?string $rawRequestBody = null;
    private bool $pluginAuthUsedQueryToken = false;
    private bool $pluginDeliveriesUsedGetMethod = false;
    private bool $pluginAckUsedLegacyToken = false;
    private ?int $pluginAuthFailureStatus = null;
    private ?string $pluginAuthFailureMessage = null;

    public function __construct()
    {
        parent::__construct();
        $this->checkins = new Checkin();
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
            $normalizedStatus = $this->normalizeStatusPayload($status);
            if (is_array($normalizedStatus)) {
                $this->persistStatusHistory($normalizedStatus, 'ws-api');
                $this->saveEndpointCache('status', $normalizedStatus);
                $this->saveSnapshotCache('status', $normalizedStatus);
                $this->saveSnapshotCache('players', $this->extractPlayersPayload($normalizedStatus));
                $this->saveSnapshotCache('chat', $this->extractChatPayload($normalizedStatus));
                $this->json($normalizedStatus);
                return;
            }
        }

        $fallback = $this->loadLatestStatusFromDatabase();
        if (is_array($fallback)) {
            $normalizedFallback = $this->normalizeStatusPayload($fallback);
            if (is_array($normalizedFallback)) {
                $this->json($normalizedFallback);
                return;
            }
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
            $this->json($this->normalizePlayersEndpointPayload($cached));
            return;
        }

        $players = $this->loadPlayersFromWsApi();
        if (is_array($players)) {
            $playersPayload = $this->normalizePlayersEndpointPayload($players);
            $this->saveEndpointCache('players', $playersPayload);
            $this->saveSnapshotCache('players', $playersPayload);
            $this->json($playersPayload);
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
            $normalizedStatus = $this->normalizeStatusPayload($status);
            if (!is_array($normalizedStatus)) {
                $normalizedStatus = $status;
            }
            $this->persistStatusHistory($normalizedStatus, 'ws-api');
            $playersPayload = $this->extractPlayersPayload($normalizedStatus);
            $this->saveEndpointCache('status', $normalizedStatus);
            $this->saveSnapshotCache('status', $normalizedStatus);
            $this->saveEndpointCache('players', $playersPayload);
            $this->saveSnapshotCache('players', $playersPayload);
            $this->saveSnapshotCache('chat', $this->extractChatPayload($normalizedStatus));
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
            $this->json($this->normalizePlayersEndpointPayload($playersSnapshot));
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
            'plugin_online' => false,
            'last_update_seconds' => $this->inferFallbackLastUpdateSeconds(),
            'players_source' => 'Fallback',
            'fallback_enabled' => true,
            'fallback_active' => true,
            'fallback_available' => true,
            'fallback' => 'Enabled',
            'checked_at' => time(),
        ];
        $this->saveEndpointCache('health', $fallback);
        $this->json($fallback);
    }

    public function getPlugins(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Cache-Control: private, max-age=' . self::PLUGINS_CACHE_TTL_SECONDS);

        $cached = $this->loadEndpointCache('plugins', self::PLUGINS_CACHE_TTL_SECONDS);
        if (is_array($cached)) {
            $this->json($this->normalizePluginsResponse($cached));
            return;
        }

        try {
            $payload = $this->loadPluginsFromWsApi();
            if (is_array($payload)) {
                $this->saveEndpointCache('plugins', $payload);
                $this->json($payload);
                return;
            }
        } catch (\Throwable $e) {
        }

        $fallback = [
            'source' => 'Fallback',
            'plugins' => [],
            'updated_at' => null,
            'error' => 'Realtime unavailable or unauthorized',
        ];
        $this->saveEndpointCache('plugins', $fallback);
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
    public function checkinStatus(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $status = $userId > 0
            ? $this->checkins->getStatusForUser($userId)
            : $this->checkins->getStatusForUser(0);

        $this->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function checkinClaim(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json([
                'success' => false,
                'code' => ApiCode::AUTH_INVALID,
                'message' => 'Login required',
            ], 401);
            return;
        }

        $this->validateCsrfToken();

        $result = $this->checkins->claimForUser(
            $userId,
            $this->getClientIp(),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        $payload = [
            'success' => (bool)($result['ok'] ?? false),
            'message' => (string)($result['message'] ?? 'Check-in failed'),
        ];

        if (isset($result['record']) && is_array($result['record'])) {
            $payload['record'] = $result['record'];
        }
        if (isset($result['delivery']) && is_array($result['delivery'])) {
            $payload['delivery'] = $result['delivery'];
        }

        $this->json($payload, (int)($result['status'] ?? 500));
    }

    public function checkinHistory(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json([
                'success' => false,
                'code' => ApiCode::AUTH_INVALID,
                'message' => 'Login required',
            ], 401);
            return;
        }

        $items = $this->checkins->getHistoryForUser($userId, 30);
        $this->json([
            'success' => true,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function checkinRewards(): void
    {
        $this->json([
            'success' => true,
            'data' => $this->checkins->getRewardRulesForCurrentMonth(),
        ]);
    }

    public function pluginCheckinDeliveries(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'POST') {
            $this->respondPluginJson([
                'success' => false,
                'message' => 'Method Not Allowed',
            ], 405);
            return;
        }

        $this->pluginDeliveriesUsedGetMethod = $method === 'GET';
        if ($this->pluginDeliveriesUsedGetMethod && !(defined('PLUGIN_ALLOW_GET_DELIVERIES') ? (bool)PLUGIN_ALLOW_GET_DELIVERIES : false)) {
            header('Allow: POST');
            $this->respondPluginJson([
                'success' => false,
                'message' => 'GET deliveries is disabled, use POST /api/plugin/checkin/deliveries',
                'expected_method' => 'POST',
                'endpoint' => '/api/plugin/checkin/deliveries',
                'hint' => 'Update StellarStatsSync CheckinDeliveryClient.fetchPendingDeliveries() to POST JSON body {"limit":20}.',
            ], 405);
            return;
        }

        if (!$this->isPluginAuthorized($this->pluginDeliveriesUsedGetMethod ? 'deliveries_get' : 'deliveries_post')) {
            $this->respondPluginJson([
                'success' => false,
                'code' => ApiCode::AUTH_INVALID,
                'message' => $this->pluginAuthFailureMessage ?? 'Unauthorized',
            ], $this->pluginAuthFailureStatus ?? 401);
            return;
        }

        try {
            $body = $this->readJsonRequestBody();
            $limit = (int)($body['limit'] ?? ($_POST['limit'] ?? ($_GET['limit'] ?? 20)));
            $items = $this->checkins->pullPendingDeliveries($limit, $this->resolvePluginAckActor());
            $deliveries = $this->formatPluginCheckinDeliveries($items);

            $this->respondPluginJson([
                'success' => true,
                'deliveries' => $deliveries,
                'count' => count($deliveries),
                'data' => [
                    'items' => $items,
                    'count' => count($items),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[Checkin] plugin deliveries failed: ' . $e->getMessage());
            $this->respondPluginJson([
                'success' => false,
                'message' => 'Checkin delivery service unavailable',
            ], 500);
        }
    }

    public function pluginCheckinDeliveriesAck(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));
        if ($method !== 'POST') {
            $this->respondPluginJson([
                'success' => false,
                'message' => 'Method Not Allowed',
            ], 405);
            return;
        }

        if (!$this->isPluginAuthorized('deliveries_ack')) {
            $this->respondPluginJson([
                'success' => false,
                'code' => ApiCode::AUTH_INVALID,
                'message' => $this->pluginAuthFailureMessage ?? 'Unauthorized',
            ], $this->pluginAuthFailureStatus ?? 401);
            return;
        }

        try {
            $body = $this->readJsonRequestBody();
            $deliveryId = (int)($body['delivery_id'] ?? ($_POST['delivery_id'] ?? 0));
            $success = $this->parseBooleanValue($body['success'] ?? ($_POST['success'] ?? null));
            $message = trim((string)($body['message'] ?? ($_POST['message'] ?? '')));
            $ackToken = $this->extractDeliveryAckToken($body);
            $ackedBy = $this->resolvePluginAckActor();

            if ($deliveryId <= 0 || $success === null) {
                $this->respondPluginJson([
                    'success' => false,
                    'message' => 'delivery_id and success are required',
                ], 400);
                return;
            }

            $result = $this->checkins->ackDelivery($deliveryId, $success, $message, $ackToken, $ackedBy);
            if (!empty($result['legacy_ack'])) {
                $this->pluginAckUsedLegacyToken = true;
                error_log('[Plugin API] Deprecated legacy ACK without ack_token detected for delivery #' . $deliveryId);
            }
            $payload = [
                'success' => (bool)($result['ok'] ?? false),
                'message' => (string)($result['message'] ?? 'Delivery ack failed'),
            ];
            if (isset($result['delivery']) && is_array($result['delivery'])) {
                $payload['delivery'] = $result['delivery'];
            }

            $this->respondPluginJson($payload, (int)($result['status'] ?? 500));
        } catch (\Throwable $e) {
            error_log('[Checkin] plugin deliveries ack failed: ' . $e->getMessage());
            $this->respondPluginJson([
                'success' => false,
                'message' => 'Checkin delivery acknowledgement unavailable',
            ], 500);
        }
    }

    private function formatPluginCheckinDeliveries(array $items): array
    {
        return array_map(static function (array $item): array {
            $deliveryId = (int)($item['delivery_id'] ?? ($item['id'] ?? 0));
            $player = isset($item['player']) && is_array($item['player']) ? $item['player'] : [];
            $username = trim((string)($item['username'] ?? ($player['username'] ?? '')));
            $mcUuid = trim((string)($item['mc_uuid'] ?? ($player['uuid'] ?? '')));
            $reward = isset($item['reward']) && is_array($item['reward']) ? $item['reward'] : [];

            if (!isset($reward['commands']) || !is_array($reward['commands'])) {
                $reward['commands'] = [];
            }

            $player['username'] = $username;
            $player['uuid'] = $mcUuid;

            return array_merge($item, [
                'id' => $deliveryId,
                'delivery_id' => $deliveryId,
                'username' => $username,
                'mc_uuid' => $mcUuid,
                'player' => $player,
                'reward' => $reward,
            ]);
        }, $items);
    }

    private function respondPluginJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        if ($this->pluginDeliveriesUsedGetMethod) {
            header('X-Deprecated-Route: GET /api/plugin/checkin/deliveries');
            header('Warning: 299 - "Deprecated: use POST /api/plugin/checkin/deliveries"', false);
        }

        if ($this->pluginAuthUsedQueryToken) {
            header('X-Deprecated-Auth: query-token');
            header('Warning: 299 - "Deprecated auth: move plugin token to request headers"', false);
        }

        if ($this->pluginAckUsedLegacyToken) {
            header('X-Deprecated-Ack: missing-ack-token');
            header('Warning: 299 - "Deprecated ack: include ack_token in delivery acknowledgement"', false);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            echo $json;
            return;
        }

        echo '{"success":false,"message":"json encode failed"}';
    }

    private function readJsonRequestBody(): array
    {
        if ($this->jsonRequestBody !== null) {
            return $this->jsonRequestBody;
        }

        $raw = $this->getRawRequestBody();
        if (!is_string($raw) || trim($raw) === '') {
            $this->jsonRequestBody = [];
            return $this->jsonRequestBody;
        }

        $decoded = json_decode($raw, true);
        $this->jsonRequestBody = is_array($decoded) ? $decoded : [];
        return $this->jsonRequestBody;
    }

    private function getRawRequestBody(): string
    {
        if ($this->rawRequestBody !== null) {
            return $this->rawRequestBody;
        }

        $raw = file_get_contents('php://input');
        $this->rawRequestBody = is_string($raw) ? $raw : '';
        return $this->rawRequestBody;
    }

    private function isPluginAuthorized(string $context = 'plugin'): bool
    {
        $this->pluginAuthFailureStatus = null;
        $this->pluginAuthFailureMessage = null;

        $tokenSource = '';
        $token = $this->extractPluginToken($tokenSource);
        $this->pluginAuthUsedQueryToken = $tokenSource === 'query';

        if ($this->pluginAuthUsedQueryToken && !(defined('PLUGIN_ALLOW_QUERY_TOKEN') ? (bool)PLUGIN_ALLOW_QUERY_TOKEN : false)) {
            $this->setPluginAuthFailure(403, 'Query token auth is disabled');
            return false;
        }

        if ($context === 'deliveries_get' && $this->pluginAuthUsedQueryToken && APP_ENV === 'production') {
            $this->setPluginAuthFailure(403, 'GET deliveries with query token is not allowed in production');
            return false;
        }

        if ($this->pluginAuthUsedQueryToken) {
            error_log('[Plugin API] Deprecated query token usage detected on ' . (string)($_SERVER['REQUEST_URI'] ?? '/api/plugin'));
        }

        if ($token === '' || !hash_equals((string)SERVER_TOKEN, $token)) {
            $this->setPluginAuthFailure(401, 'Unauthorized');
            return false;
        }

        if ((defined('PLUGIN_REQUIRE_HMAC') ? (bool)PLUGIN_REQUIRE_HMAC : false) && !$this->verifyPluginRequestHmac()) {
            return false;
        }

        return true;
    }

    private function setPluginAuthFailure(int $status, string $message): void
    {
        $this->pluginAuthFailureStatus = $status;
        $this->pluginAuthFailureMessage = $message;
    }

    private function verifyPluginRequestHmac(): bool
    {
        $timestampRaw = trim((string)($_SERVER['HTTP_X_STELLAR_TIMESTAMP'] ?? ''));
        $nonce = trim((string)($_SERVER['HTTP_X_STELLAR_NONCE'] ?? ''));
        $signature = trim((string)($_SERVER['HTTP_X_STELLAR_SIGNATURE'] ?? ''));

        if ($timestampRaw === '' || $nonce === '' || $signature === '') {
            $this->setPluginAuthFailure(401, 'Missing HMAC signature headers');
            return false;
        }

        if (preg_match('/^\d{10,13}$/', $timestampRaw) !== 1) {
            $this->setPluginAuthFailure(401, 'Invalid HMAC timestamp');
            return false;
        }

        $timestamp = (int)$timestampRaw;
        if (strlen($timestampRaw) > 10) {
            $timestamp = (int)floor($timestamp / 1000);
        }

        $timeWindow = max(60, (int)(defined('PLUGIN_HMAC_TIME_WINDOW_SECONDS') ? PLUGIN_HMAC_TIME_WINDOW_SECONDS : 300));
        if (abs(time() - $timestamp) > $timeWindow) {
            $this->setPluginAuthFailure(401, 'HMAC timestamp outside allowed window');
            return false;
        }

        if (strlen($nonce) > 128) {
            $this->setPluginAuthFailure(401, 'Invalid HMAC nonce');
            return false;
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $bodyHash = hash('sha256', $this->getRawRequestBody());
        $canonical = $method . "\n" . $path . "\n" . $timestampRaw . "\n" . $nonce . "\n" . $bodyHash;

        $expected = hash_hmac('sha256', $canonical, (string)SERVER_TOKEN);
        if (!hash_equals(strtolower($expected), strtolower($signature))) {
            $this->setPluginAuthFailure(401, 'Invalid HMAC signature');
            return false;
        }

        return true;
    }

    private function extractPluginToken(?string &$source = null): string
    {
        $body = $this->readJsonRequestBody();

        $authorization = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($authorization !== '') {
            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
                $source = 'authorization_bearer';
                return trim((string)$matches[1]);
            }

            $source = 'authorization_raw';
            return $authorization;
        }

        // Preferred auth carriers for plugin callers:
        // 1) Authorization: Bearer <token>
        // 2) X-Plugin-Token / X-Stellar-Plugin-Token
        foreach ([
            'HTTP_X_PLUGIN_TOKEN',
            'HTTP_X_STELLAR_PLUGIN_TOKEN',
            // Legacy headers retained for compatibility.
            'HTTP_X_API_TOKEN',
            'HTTP_X_SERVER_TOKEN',
        ] as $key) {
            $header = trim((string)($_SERVER[$key] ?? ''));
            if ($header !== '') {
                $source = strtolower(str_replace('HTTP_', '', $key));
                return $header;
            }
        }

        $bodyToken = trim((string)($body['token'] ?? $_POST['token'] ?? ''));
        if ($bodyToken !== '') {
            $source = 'body';
            return $bodyToken;
        }

        $queryToken = trim((string)($_GET['token'] ?? ''));
        if ($queryToken !== '') {
            $source = 'query';
            return $queryToken;
        }

        $source = 'none';
        $token = '';
        return trim((string)$token);
    }

    private function extractDeliveryAckToken(array $body): ?string
    {
        foreach ([
            'HTTP_X_STELLAR_ACK_TOKEN',
            'HTTP_X_PLUGIN_ACK_TOKEN',
            'HTTP_X_DELIVERY_ACK_TOKEN',
        ] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $bodyValue = trim((string)($body['ack_token'] ?? ($_POST['ack_token'] ?? '')));
        if ($bodyValue !== '') {
            return $bodyValue;
        }

        return null;
    }

    private function resolvePluginAckActor(): ?string
    {
        foreach ([
            'HTTP_X_STELLAR_SERVER_ID',
            'HTTP_X_SERVER_ID',
            'HTTP_X_PLUGIN_INSTANCE',
            'HTTP_X_PLUGIN_NAME',
        ] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, 128);
            }
        }

        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr !== '') {
            return 'ip:' . mb_substr($remoteAddr, 0, 100);
        }

        return null;
    }

    private function parseBooleanValue($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }
        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }

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

        $config = LeaderboardSnapshot::resolveBoardConfig($boardKey);
        if ($config === null) {
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Unknown board'], 400);
            return;
        }

        $safeColumn = LeaderboardSnapshot::safeColumnIdentifier((string)$config['column']);
        if ($safeColumn === null) {
            $this->json(['success' => false, 'code' => ApiCode::SERVER_ERROR, 'message' => 'Board misconfigured', 'results' => []], 500);
            return;
        }

        $format = (string)$config['format'];
        $unit = (string)$config['unit'];

        try {
            $db = Database::connection();
            $stmt = $db->query(
                "SELECT mc_uuid, username, {$safeColumn} AS metric FROM player_stats ORDER BY {$safeColumn} DESC LIMIT 2000"
            );
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
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_MAXREDIRS => 0,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; avatar-proxy/1.0)',
                ]);
                if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
                if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
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
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
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
        $payload = $this->fetchWsApiJson('/api/status', true);
        if (!is_array($payload)) {
            return null;
        }

        return $this->normalizeStatusPayload($payload);
    }

    private function loadPlayersFromWsApi(): ?array
    {
        $payload = $this->fetchWsApiJson('/api/players', true);
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
        $payload = $this->fetchWsApiJson('/api/chat', true);
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

    private function loadPluginsFromWsApi(): ?array
    {
        $payload = $this->fetchWsApiJson('/api/status', true);
        if (!is_array($payload)) {
            return null;
        }

        $plugins = $this->extractPluginsFromStatusPayload($payload);
        $updatedAt = $this->extractPluginsUpdatedAt($payload);
        if ($plugins !== [] && $updatedAt === null) {
            $updatedAt = time();
        }

        return [
            'source' => 'WS',
            'plugins' => $plugins,
            'updated_at' => $plugins === [] ? null : $updatedAt,
        ];
    }

    private function normalizeRealtimeHealthPayload(array $payload, bool $requestOk): array
    {
        $candidate = $this->unwrapApiPayload($payload);
        $checkedAt = $this->resolveCheckedAt($candidate);

        $apiOk = $this->extractHealthApiOk($candidate, $requestOk);
        $pluginOnline = $this->extractHealthPluginOnline($candidate, $apiOk);
        $fallbackActive = $this->extractHealthFallbackActive($candidate, !($apiOk && $pluginOnline));
        $fallbackEnabled = $this->extractHealthFallbackEnabled($candidate, $fallbackActive);
        $fallbackAvailable = $this->extractHealthFallbackAvailable($candidate, true);

        if ($apiOk && $pluginOnline) {
            $fallbackActive = false;
        }
        if ($fallbackActive) {
            $fallbackEnabled = true;
            $fallbackAvailable = true;
        } elseif ($fallbackEnabled) {
            $fallbackAvailable = true;
        }

        $playersSource = $this->normalizePlayersSource(
            $this->firstAvailableValue($candidate, ['players_source', 'playersSource', 'source', 'source_type']),
            $fallbackActive,
            $apiOk,
            $pluginOnline
        );
        $lastUpdateSeconds = $this->resolveLastUpdateSeconds($candidate, $checkedAt);
        $fallbackText = $fallbackActive ? 'Enabled' : 'Disabled';

        return [
            'realtime_api' => $apiOk ? 'OK' : 'ERROR',
            'plugin' => $pluginOnline ? 'Online' : 'Offline',
            'plugin_online' => $pluginOnline,
            'last_update_seconds' => $lastUpdateSeconds,
            'players_source' => $playersSource,
            'fallback_enabled' => $fallbackEnabled,
            'fallback_active' => $fallbackActive,
            'fallback_available' => $fallbackAvailable,
            'fallback' => $fallbackText,
            'checked_at' => $checkedAt,
        ];
    }

    private function extractHealthApiOk(array $candidate, bool $default): bool
    {
        $apiState = $this->firstAvailableValue(
            $candidate,
            ['realtime_api', 'realtimeApi', 'api', 'api_status', 'apiStatus', 'status', 'state', 'ok', 'healthy']
        );
        $resolved = $this->parseHealthBool($apiState);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function extractHealthPluginOnline(array $candidate, bool $default): bool
    {
        $connections = isset($candidate['connections']) && is_array($candidate['connections']) ? $candidate['connections'] : [];
        $pluginConnections = $this->firstAvailableValue($connections, ['plugin', 'plugins', 'plugin_count', 'pluginCount']);
        if (is_numeric($pluginConnections)) {
            return ((int)$pluginConnections) > 0;
        }
        $connectionState = $this->parseHealthBool($pluginConnections);
        if ($connectionState !== null) {
            return $connectionState;
        }

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
            ['fallback_enabled', 'fallbackEnabled', 'fallback', 'degraded']
        );
        $resolved = $this->parseHealthBool($fallbackValue);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function extractHealthFallbackActive(array $candidate, bool $default): bool
    {
        $fallbackValue = $this->firstAvailableValue(
            $candidate,
            ['fallback_active', 'fallbackActive', 'using_fallback', 'usingFallback']
        );
        $resolved = $this->parseHealthBool($fallbackValue);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function extractHealthFallbackAvailable(array $candidate, bool $default): bool
    {
        $fallbackValue = $this->firstAvailableValue(
            $candidate,
            ['fallback_available', 'fallbackAvailable']
        );
        $resolved = $this->parseHealthBool($fallbackValue);
        if ($resolved !== null) {
            return $resolved;
        }

        return $default;
    }

    private function normalizePlayersSource($rawSource, bool $fallbackActive, bool $apiOk, bool $pluginOnline): string
    {
        if ($apiOk && $pluginOnline) {
            return 'WS';
        }

        if (is_string($rawSource) && trim($rawSource) !== '') {
            $normalized = strtolower(trim($rawSource));
            if (str_contains($normalized, 'fallback') || str_contains($normalized, 'cache') || str_contains($normalized, 'db')) {
                return 'Fallback';
            }
            if (str_contains($normalized, 'ws')) {
                return $pluginOnline ? 'WS' : 'Fallback';
            }
        }

        return 'Fallback';
    }

    private function resolveLastUpdateSeconds(array $candidate, int $nowTs): ?int
    {
        $secondsValue = $this->firstAvailableValue($candidate, ['last_update_seconds', 'lastUpdateSeconds', 'age_seconds', 'ageSeconds']);
        if (is_numeric($secondsValue)) {
            $seconds = max(0, (int)$secondsValue);
            return $seconds;
        }

        $ambiguousLastUpdate = $this->firstAvailableValue($candidate, ['last_update', 'lastUpdate']);
        if (is_numeric($ambiguousLastUpdate)) {
            $ambiguousNumber = (float)$ambiguousLastUpdate;
            if ($ambiguousNumber >= 0 && $ambiguousNumber < 1000000000) {
                return max(0, (int)round($ambiguousNumber));
            }
        }

        $timestampValue = $this->firstAvailableValue(
            $candidate,
            ['updated_at', 'updatedAt', 'last_update_at', 'lastUpdateAt', 'last_plugin_seen_at', 'lastPluginSeenAt', 'last_plugin_update_at', 'lastPluginUpdateAt', 'checked_at', 'checkedAt']
        );
        $timestamp = $this->parseUnixTimestamp($timestampValue);
        if ($timestamp !== null) {
            return max(0, $nowTs - $timestamp);
        }

        if (is_string($ambiguousLastUpdate) && trim($ambiguousLastUpdate) !== '') {
            $ambiguousTimestamp = $this->parseUnixTimestamp($ambiguousLastUpdate);
            if ($ambiguousTimestamp !== null) {
                return max(0, $nowTs - $ambiguousTimestamp);
            }
        }

        return null;
    }

    private function resolveCheckedAt(array $candidate): int
    {
        $checkedAtValue = $this->firstAvailableValue($candidate, ['checked_at', 'checkedAt']);
        $checkedAt = $this->parseUnixTimestamp($checkedAtValue);
        if ($checkedAt === null || $checkedAt <= 0) {
            return time();
        }

        return $checkedAt;
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
            $baseUrl = 'http://127.0.0.1:3001';
        }

        return rtrim($baseUrl, '/');
    }

    private function wsStatusApiToken(): string
    {
        return defined('WS_STATUS_API_TOKEN') ? trim((string)WS_STATUS_API_TOKEN) : '';
    }

    private function buildWsStatusRequestHeaders(bool $withToken): array
    {
        $headers = ['Accept: application/json'];
        if (!$withToken) {
            return $headers;
        }

        $token = $this->wsStatusApiToken();
        if ($token === '') {
            return $headers;
        }

        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'x-api-token: ' . $token;

        return $headers;
    }

    private function wsStatusApiTimeoutSeconds(): int
    {
        $timeoutMs = defined('WS_STATUS_API_TIMEOUT_MS') ? (int)WS_STATUS_API_TIMEOUT_MS : 2500;
        return max(1, (int)ceil(max(500, $timeoutMs) / 1000));
    }

    private function fetchWsApiJson(string $path, bool $withToken = false): ?array
    {
        $url = $this->wsStatusApiBaseUrl() . '/' . ltrim($path, '/');
        return $this->fetchJsonFromUrl(
            $url,
            $this->wsStatusApiTimeoutSeconds(),
            $this->buildWsStatusRequestHeaders($withToken)
        );
    }

    private function fetchJsonFromUrl(string $url, int $timeoutSeconds, array $headers = []): ?array
    {
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $responseBody = null;
        $httpCode = 0;
        $requestHeaders = $headers === [] ? ['Accept: application/json'] : $headers;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_MAXREDIRS => 0,
                    CURLOPT_CONNECTTIMEOUT => min(3, max(1, $timeoutSeconds)),
                    CURLOPT_TIMEOUT => $timeoutSeconds,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HTTPHEADER => $requestHeaders,
                    CURLOPT_USERAGENT => 'stellarvan-status-client/1.0',
                ]);
                if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
                if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
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
                    'follow_location' => 0,
                    'max_redirects' => 0,
                    'header' => implode("\r\n", $requestHeaders) . "\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
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
            $candidate = array_replace($candidate, $candidate['status']);
        }

        if (!$this->isAssocArray($candidate)) {
            $candidate = ['players' => ['list' => $candidate]];
        }

        $statePlayers = $this->extractVersionedModuleData($candidate, 'players');
        $stateStats = $this->extractVersionedModuleData($candidate, 'stats');
        $stateServer = $this->extractVersionedModuleData($candidate, 'server');
        $statePlugins = $this->extractVersionedModuleData($candidate, 'plugins');
        $stateChat = $this->extractVersionedModuleData($candidate, 'chat');

        $hasStatusSignals = array_key_exists('online', $candidate)
            || array_key_exists('players', $candidate)
            || array_key_exists('stats', $candidate)
            || array_key_exists('metrics', $candidate)
            || array_key_exists('server', $candidate)
            || array_key_exists('serverInfo', $candidate)
            || array_key_exists('motd', $candidate)
            || array_key_exists('version', $candidate)
            || $statePlayers !== null
            || $stateStats !== null
            || $stateServer !== null;
        if (!$hasStatusSignals) {
            return null;
        }

        $serverNode = [];
        if (isset($candidate['server']) && is_array($candidate['server'])) {
            $serverNode = $candidate['server'];
        } elseif (is_array($stateServer)) {
            $serverNode = $stateServer;
        } elseif (isset($candidate['serverInfo']) && is_array($candidate['serverInfo'])) {
            $serverNode = $candidate['serverInfo'];
        }

        $playersSource = $candidate['players'] ?? $statePlayers;
        if ($playersSource === null && isset($candidate['sample']) && is_array($candidate['sample'])) {
            $playersSource = ['list' => $candidate['sample']];
        }

        $statsSource = $candidate['stats'] ?? null;
        if ($statsSource === null) {
            $statsSource = $candidate['metrics'] ?? null;
        }
        if ($statsSource === null) {
            $statsSource = $stateStats;
        }

        $players = $this->normalizeStatusPlayers($playersSource, is_array($statsSource) ? $statsSource : []);
        $stats = $this->normalizeStatusStats($statsSource, $players);
        if ($stats['tps'] === null && array_key_exists('tps', $candidate)) {
            $stats['tps'] = $this->normalizeNullableFloat($candidate['tps']);
        }
        if ($stats['mspt'] === null && array_key_exists('mspt', $candidate)) {
            $stats['mspt'] = $this->normalizeNullableFloat($candidate['mspt']);
        }

        $motd = $this->normalizeStatusMotd(
            $candidate['motd'] ?? null,
            $serverNode['motd'] ?? ($candidate['serverInfo']['motd'] ?? null)
        );
        $version = $this->normalizeStatusVersion(
            $candidate['version'] ?? null,
            $serverNode['version'] ?? null
        );

        $onlineRaw = array_key_exists('online', $candidate) ? $candidate['online'] : null;
        if ($onlineRaw === null) {
            $onlineRaw = $this->firstAvailableValue($serverNode, ['online', 'isOnline', 'up', 'healthy', 'ok']);
        }
        $onlineParsed = $this->parseHealthBool($onlineRaw);
        $online = $onlineParsed !== null ? $onlineParsed : (($players['online'] > 0) || ($stats['onlinePlayers'] > 0));

        $serverOnlineRaw = $this->firstAvailableValue($serverNode, ['online', 'isOnline', 'up', 'healthy', 'ok']);
        $serverOnlineParsed = $this->parseHealthBool($serverOnlineRaw);
        $serverOnline = $serverOnlineParsed !== null ? $serverOnlineParsed : $online;
        $serverMotd = $this->normalizeStatusMotd($serverNode['motd'] ?? null, $motd);
        $serverVersion = $this->normalizeStatusVersion($serverNode['version'] ?? null, $version);

        $pluginsSource = $candidate['plugins'] ?? $statePlugins;
        $plugins = is_array($pluginsSource) ? $this->normalizePluginList($pluginsSource) : [];

        $chatSource = $candidate['chat'] ?? null;
        if ($chatSource === null && isset($candidate['messages']) && is_array($candidate['messages'])) {
            $chatSource = $candidate['messages'];
        }
        if ($chatSource === null) {
            $chatSource = $stateChat;
        }
        $chat = is_array($chatSource) ? $this->sanitizeChatPayload($chatSource) : [];

        $updatedAtRaw = $this->firstAvailableValue(
            $candidate,
            ['updatedAt', 'updated_at', 'timestamp', 'time', 'checked_at', 'checkedAt', 'last_update', 'lastUpdate']
        );
        if ($updatedAtRaw === null && is_array($serverNode)) {
            $updatedAtRaw = $this->firstAvailableValue(
                $serverNode,
                ['updatedAt', 'updated_at', 'timestamp', 'time', 'checked_at', 'checkedAt', 'last_update', 'lastUpdate']
            );
        }
        $updatedAt = $this->parseUnixTimestamp($updatedAtRaw);

        $sourceRaw = $this->firstAvailableValue($candidate, ['source', 'sourceType', 'source_type', 'provider']);
        $source = is_scalar($sourceRaw) ? trim((string)$sourceRaw) : '';
        if ($source === '') {
            $source = 'ws-api';
        }

        $normalized = [
            'online' => $online,
            'players' => $players,
            'motd' => $motd,
            'version' => $version,
            'server' => [
                'online' => $serverOnline,
                'motd' => $serverMotd,
                'version' => $serverVersion,
            ],
            'stats' => $stats,
            'plugins' => $plugins,
            'chat' => $chat,
            'updatedAt' => $updatedAt,
            'source' => $source,
            'messages' => $chat,
        ];

        foreach (['world', 'address', 'serverId', 'serverName', 'platform', 'updatedAt'] as $optionalKey) {
            if (array_key_exists($optionalKey, $serverNode)) {
                $normalized['server'][$optionalKey] = $serverNode[$optionalKey];
            }
        }

        $iconRaw = $candidate['icon'] ?? ($candidate['favicon'] ?? ($serverNode['icon'] ?? ($serverNode['favicon'] ?? null)));
        if (is_scalar($iconRaw)) {
            $icon = trim((string)$iconRaw);
            if ($icon !== '') {
                $normalized['icon'] = $icon;
            }
        }

        if ($stats['tps'] !== null) {
            $normalized['tps'] = $stats['tps'];
        }

        return $normalized;
    }

    private function extractVersionedModuleData(array $payload, string $module)
    {
        $module = trim($module);
        if ($module === '') {
            return null;
        }

        $moduleNode = $this->readNestedPath($payload, ['state', $module]);
        if ($moduleNode === null) {
            $moduleNode = $this->readNestedPath($payload, ['payload', 'state', $module]);
        }
        if ($moduleNode === null) {
            return null;
        }

        if (is_array($moduleNode) && $this->isAssocArray($moduleNode) && array_key_exists('data', $moduleNode)) {
            return $moduleNode['data'];
        }

        return $moduleNode;
    }

    private function normalizeStatusPlayers($players, array $stats = []): array
    {
        if (is_array($players) && $this->isAssocArray($players) && array_key_exists('version', $players) && array_key_exists('data', $players)) {
            $players = $players['data'];
        }

        $listSource = [];
        $onlineRaw = null;
        $maxRaw = null;

        if (is_array($players)) {
            if ($this->isAssocArray($players)) {
                $onlineRaw = $this->firstAvailableValue($players, ['online', 'onlinePlayers', 'playerCount', 'player_count']);
                $maxRaw = $this->firstAvailableValue($players, ['max', 'maxPlayers', 'playerMax', 'player_max']);

                if (isset($players['list']) && is_array($players['list'])) {
                    $listSource = $players['list'];
                } elseif (isset($players['sample']) && is_array($players['sample'])) {
                    $listSource = $players['sample'];
                } elseif (isset($players['players']) && is_array($players['players']) && !$this->isAssocArray($players['players'])) {
                    $listSource = $players['players'];
                } elseif (isset($players['data']) && is_array($players['data']) && !$this->isAssocArray($players['data'])) {
                    $listSource = $players['data'];
                }
            } else {
                $listSource = $players;
            }
        }

        $list = $this->normalizePlayersList($listSource);

        $online = $this->normalizeNullableInt($onlineRaw);
        if ($online === null) {
            $online = $this->normalizeNullableInt($this->firstAvailableValue($stats, ['onlinePlayers', 'online_players', 'online', 'playerCount']));
        }
        if ($online === null) {
            $online = count($list);
        }
        $online = max(0, $online);
        if ($online === 0 && count($list) > 0) {
            $online = count($list);
        }

        $max = null;
        $maxInt = $this->normalizeNullableInt($maxRaw);
        if ($maxInt !== null) {
            $max = max(0, $maxInt);
        } elseif (is_scalar($maxRaw)) {
            $maxText = trim((string)$maxRaw);
            if ($maxText !== '') {
                $max = $maxText;
            }
        }
        if ($max === null) {
            $statsMaxRaw = $this->firstAvailableValue($stats, ['maxPlayers', 'max_players', 'max', 'playerMax']);
            $statsMaxInt = $this->normalizeNullableInt($statsMaxRaw);
            if ($statsMaxInt !== null) {
                $max = max(0, $statsMaxInt);
            } elseif (is_scalar($statsMaxRaw)) {
                $statsMaxText = trim((string)$statsMaxRaw);
                if ($statsMaxText !== '') {
                    $max = $statsMaxText;
                }
            }
        }

        return [
            'online' => $online,
            'max' => $max,
            'list' => $list,
        ];
    }

    private function normalizeStatusStats($stats, array $players = []): array
    {
        $statsNode = [];
        if (is_array($stats)) {
            if ($this->isAssocArray($stats) && array_key_exists('version', $stats) && array_key_exists('data', $stats) && is_array($stats['data'])) {
                $statsNode = $stats['data'];
            } elseif ($this->isAssocArray($stats)) {
                $statsNode = $stats;
            }
        }

        $onlinePlayers = $this->normalizeNullableInt(
            $this->firstAvailableValue($statsNode, ['onlinePlayers', 'online_players', 'playerCount', 'player_count', 'online'])
        );
        if ($onlinePlayers === null) {
            $onlinePlayers = $this->normalizeNullableInt($players['online'] ?? null);
        }
        if ($onlinePlayers === null) {
            $onlinePlayers = isset($players['list']) && is_array($players['list']) ? count($players['list']) : 0;
        }
        $onlinePlayers = max(0, $onlinePlayers);

        $maxPlayers = $this->normalizeNullableInt(
            $this->firstAvailableValue($statsNode, ['maxPlayers', 'max_players', 'max', 'playerMax', 'player_max'])
        );
        if ($maxPlayers === null) {
            $maxPlayers = $this->normalizeNullableInt($players['max'] ?? null);
        }
        if ($maxPlayers !== null) {
            $maxPlayers = max(0, $maxPlayers);
        }

        $tps = $this->normalizeNullableFloat($this->firstAvailableValue($statsNode, ['tps', 'ticksPerSecond']));
        if ($tps !== null && $tps < 0) {
            $tps = null;
        }

        $mspt = $this->normalizeNullableFloat($this->firstAvailableValue($statsNode, ['mspt', 'millisecondsPerTick']));
        if ($mspt !== null && $mspt < 0) {
            $mspt = null;
        }

        $cpuUsage = $this->normalizeNullableFloat($this->firstAvailableValue($statsNode, ['cpuUsage', 'cpu_usage', 'cpu']));
        if ($cpuUsage !== null && $cpuUsage < 0) {
            $cpuUsage = null;
        }

        $memoryUsedMb = $this->normalizeNullableInt($this->firstAvailableValue($statsNode, ['memoryUsedMb', 'memory_used_mb', 'memoryUsed']));
        if ($memoryUsedMb !== null) {
            $memoryUsedMb = max(0, $memoryUsedMb);
        }

        $memoryMaxMb = $this->normalizeNullableInt($this->firstAvailableValue($statsNode, ['memoryMaxMb', 'memory_max_mb', 'memoryMax']));
        if ($memoryMaxMb !== null) {
            $memoryMaxMb = max(0, $memoryMaxMb);
        }

        $uptimeSeconds = $this->normalizeNullableInt($this->firstAvailableValue($statsNode, ['uptimeSeconds', 'uptime_seconds', 'uptime']));
        if ($uptimeSeconds !== null) {
            $uptimeSeconds = max(0, $uptimeSeconds);
        }

        return [
            'onlinePlayers' => $onlinePlayers,
            'maxPlayers' => $maxPlayers,
            'tps' => $tps,
            'mspt' => $mspt,
            'cpuUsage' => $cpuUsage,
            'memoryUsedMb' => $memoryUsedMb,
            'memoryMaxMb' => $memoryMaxMb,
            'uptimeSeconds' => $uptimeSeconds,
        ];
    }

    private function normalizeStatusMotd($motd, $serverMotd = null): array
    {
        $motdNode = $motd;
        if (!$this->isSupportedMotdNode($motdNode)) {
            $motdNode = $serverMotd;
        }

        $source = 'legacy';
        $format = 'plain';
        $clean = '';
        $plain = '';
        $miniMessage = '';
        $html = '';
        $lines = [];

        if (is_scalar($motdNode) && !is_array($motdNode)) {
            $clean = trim((string)$motdNode);
            $plain = $clean;
        } elseif (is_array($motdNode) && $this->isAssocArray($motdNode)) {
            $sourceRaw = $this->firstAvailableValue($motdNode, ['source', 'provider']);
            if (is_scalar($sourceRaw) && trim((string)$sourceRaw) !== '') {
                $source = trim((string)$sourceRaw);
            } else {
                $source = 'plugin';
            }

            $formatRaw = $this->firstAvailableValue($motdNode, ['format', 'type']);
            if (is_scalar($formatRaw) && trim((string)$formatRaw) !== '') {
                $format = trim((string)$formatRaw);
            }

            $cleanRaw = $this->firstAvailableValue($motdNode, ['clean', 'plain', 'text', 'line', 'message']);
            if (is_scalar($cleanRaw)) {
                $clean = trim((string)$cleanRaw);
            }

            $plainRaw = $this->firstAvailableValue($motdNode, ['plain', 'clean', 'text']);
            if (is_scalar($plainRaw)) {
                $plain = trim((string)$plainRaw);
            }

            $miniMessageRaw = $this->firstAvailableValue($motdNode, ['miniMessage', 'mini_message', 'minimessage']);
            if (is_scalar($miniMessageRaw)) {
                $miniMessage = trim((string)$miniMessageRaw);
            }

            $htmlRaw = $this->firstAvailableValue($motdNode, ['html']);
            if (is_scalar($htmlRaw)) {
                $html = trim((string)$htmlRaw);
            }

            $lines = $this->normalizeStatusMotdLines($motdNode['lines'] ?? null);
            if ($lines === []) {
                $lines = $this->normalizeStatusMotdLines($motdNode['raw'] ?? null);
            }
            if ($lines === [] && array_key_exists('line', $motdNode)) {
                $lines = $this->normalizeStatusMotdLines([$motdNode['line']]);
            }
        } elseif (is_array($motdNode)) {
            $lines = $this->normalizeStatusMotdLines($motdNode);
        }

        if ($lines !== []) {
            if ($clean === '') {
                $clean = $this->joinMotdLines($lines, 'clean', ' ');
            }
            if ($plain === '') {
                $plain = $this->joinMotdLines($lines, 'plain', ' ');
            }
            if ($miniMessage === '') {
                $miniMessage = $this->joinMotdLines($lines, 'miniMessage', "\n");
            }
            if ($html === '') {
                $html = $this->joinMotdLines($lines, 'html', '<br>');
            }
        }

        if ($format === 'plain') {
            if ($miniMessage !== '' || $this->joinMotdLines($lines, 'miniMessage', '') !== '') {
                $format = 'minimessage';
            } elseif ($html !== '') {
                $format = 'html';
            }
        }

        if ($plain === '' && $clean !== '') {
            $plain = $clean;
        }
        if ($clean === '' && $plain !== '') {
            $clean = $plain;
        }

        return [
            'source' => $source,
            'format' => $format,
            'clean' => $clean,
            'plain' => $plain,
            'html' => $html,
            'miniMessage' => $miniMessage,
            'lines' => $lines,
        ];
    }

    private function normalizeStatusMotdLines($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $lines = [];
        foreach ($value as $lineNode) {
            if (is_array($lineNode) && !$this->isAssocArray($lineNode)) {
                foreach ($lineNode as $nestedLine) {
                    $normalizedNestedLine = $this->normalizeStatusMotdLine($nestedLine);
                    if (is_array($normalizedNestedLine)) {
                        $lines[] = $normalizedNestedLine;
                    }
                }
                continue;
            }

            $normalizedLine = $this->normalizeStatusMotdLine($lineNode);
            if (is_array($normalizedLine)) {
                $lines[] = $normalizedLine;
            }
        }

        return $lines;
    }

    private function normalizeStatusMotdLine($lineNode): ?array
    {
        $clean = '';
        $plain = '';
        $miniMessage = '';
        $html = '';

        if (is_scalar($lineNode) && !is_array($lineNode)) {
            $clean = trim((string)$lineNode);
            $plain = $clean;
        } elseif (is_array($lineNode) && $this->isAssocArray($lineNode)) {
            $cleanRaw = $this->firstAvailableValue($lineNode, ['clean', 'plain', 'text', 'line', 'message']);
            if (is_scalar($cleanRaw)) {
                $clean = trim((string)$cleanRaw);
            }

            $plainRaw = $this->firstAvailableValue($lineNode, ['plain', 'clean', 'text']);
            if (is_scalar($plainRaw)) {
                $plain = trim((string)$plainRaw);
            }

            $miniMessageRaw = $this->firstAvailableValue($lineNode, ['miniMessage', 'mini_message', 'minimessage']);
            if (is_scalar($miniMessageRaw)) {
                $miniMessage = trim((string)$miniMessageRaw);
            }

            $htmlRaw = $this->firstAvailableValue($lineNode, ['html']);
            if (is_scalar($htmlRaw)) {
                $html = trim((string)$htmlRaw);
            }
        }

        if ($plain === '' && $clean !== '') {
            $plain = $clean;
        }
        if ($clean === '' && $plain !== '') {
            $clean = $plain;
        }

        if ($clean === '' && $plain === '' && $miniMessage === '' && $html === '') {
            return null;
        }

        return [
            'clean' => $clean,
            'plain' => $plain,
            'miniMessage' => $miniMessage,
            'html' => $html,
        ];
    }

    private function joinMotdLines(array $lines, string $key, string $glue): string
    {
        $parts = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $value = $line[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }

            $text = trim((string)$value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode($glue, $parts);
    }

    private function normalizeStatusVersion($version, $serverVersion = null): string
    {
        foreach ([$version, $serverVersion] as $candidate) {
            if (is_scalar($candidate) && !is_array($candidate)) {
                $value = trim((string)$candidate);
                if ($value !== '') {
                    return $value;
                }
                continue;
            }

            if (!is_array($candidate)) {
                continue;
            }

            if ($candidate !== [] && !$this->isAssocArray($candidate)) {
                foreach ($candidate as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $value = trim((string)$item);
                    if ($value !== '') {
                        return $value;
                    }
                }
                continue;
            }

            foreach (['name_clean', 'name', 'text', 'protocol', 'version'] as $key) {
                if (!array_key_exists($key, $candidate) || !is_scalar($candidate[$key])) {
                    continue;
                }
                $value = trim((string)$candidate[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function normalizeNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int)round($value);
        }
        if (is_numeric($value)) {
            return (int)round((float)$value);
        }
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $text)) {
            return null;
        }

        return (int)round((float)$text);
    }

    private function normalizeNullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return (float)$value;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $text)) {
            return null;
        }

        return (float)$text;
    }

    private function isSupportedMotdNode($value): bool
    {
        if (is_scalar($value) && !is_array($value)) {
            return trim((string)$value) !== '';
        }

        if (!is_array($value)) {
            return false;
        }

        return $this->isAssocArray($value) || $value !== [];
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
        $normalizedStatus = $this->normalizeStatusPayload($payload);
        if (is_array($normalizedStatus)) {
            return $normalizedStatus;
        }

        return $this->defaultOfflineStatus();
    }

    private function extractPlayersPayload(array $statusPayload): array
    {
        $normalized = $this->normalizeStatusPayload($statusPayload);
        if (is_array($normalized) && isset($normalized['players']) && is_array($normalized['players'])) {
            return $this->normalizePlayersEndpointPayload($normalized['players']);
        }

        return $this->normalizePlayersEndpointPayload(['online' => 0, 'max' => null, 'list' => []]);
    }

    private function normalizePlayersEndpointPayload(array $payload): array
    {
        $normalized = $this->normalizeStatusPlayers($payload);

        return [
            'online' => $normalized['online'],
            'max' => $normalized['max'],
            'list' => $normalized['list'],
            'players' => $normalized['list'],
        ];
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

    private function normalizePluginsResponse(array $payload): array
    {
        $sourceRaw = is_scalar($payload['source'] ?? null) ? (string)$payload['source'] : '';
        $source = strcasecmp(trim($sourceRaw), 'WS') === 0 ? 'WS' : 'Fallback';
        $plugins = isset($payload['plugins']) && is_array($payload['plugins'])
            ? $this->normalizePluginList($payload['plugins'])
            : [];

        $updatedAt = $this->parseUnixTimestamp(
            $this->firstAvailableValue($payload, ['updated_at', 'updatedAt', 'timestamp', 'checked_at'])
        );
        if ($plugins === []) {
            $updatedAt = null;
        }

        $normalized = [
            'source' => $source,
            'plugins' => $plugins,
            'updated_at' => $updatedAt,
        ];

        if ($source === 'Fallback') {
            $error = trim((string)($payload['error'] ?? 'Realtime unavailable or unauthorized'));
            $normalized['error'] = $error === '' ? 'Realtime unavailable or unauthorized' : $error;
        }

        return $normalized;
    }

    private function extractPluginsFromStatusPayload(array $payload): array
    {
        $candidatePaths = [
            ['plugins'],
            ['data', 'plugins'],
            ['state', 'plugins', 'data'],
            ['snapshot', 'plugins'],
            ['payload', 'plugins'],
            ['payload', 'state', 'plugins', 'data'],
        ];

        foreach ($candidatePaths as $path) {
            $candidate = $this->readNestedPath($payload, $path);
            if (is_array($candidate)) {
                return $this->normalizePluginList($candidate);
            }
        }

        return [];
    }

    private function extractPluginsUpdatedAt(array $payload): ?int
    {
        $timestampKeys = [
            'updated_at',
            'updatedAt',
            'timestamp',
            'time',
            'checked_at',
            'last_update',
            'lastUpdate',
            'last_plugin_seen_at',
            'lastPluginSeenAt',
        ];

        $candidateNodes = [
            $payload,
            $this->readNestedPath($payload, ['data']),
            $this->readNestedPath($payload, ['state']),
            $this->readNestedPath($payload, ['snapshot']),
            $this->readNestedPath($payload, ['payload']),
            $this->readNestedPath($payload, ['payload', 'state']),
            $this->readNestedPath($payload, ['plugins']),
            $this->readNestedPath($payload, ['state', 'plugins']),
            $this->readNestedPath($payload, ['payload', 'plugins']),
            $this->readNestedPath($payload, ['payload', 'state', 'plugins']),
        ];

        foreach ($candidateNodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $value = $this->firstAvailableValue($node, $timestampKeys);
            $timestamp = $this->parseUnixTimestamp($value);
            if ($timestamp !== null) {
                return $timestamp;
            }

            if (isset($node['data']) && is_array($node['data'])) {
                $nestedValue = $this->firstAvailableValue($node['data'], $timestampKeys);
                $nestedTimestamp = $this->parseUnixTimestamp($nestedValue);
                if ($nestedTimestamp !== null) {
                    return $nestedTimestamp;
                }
            }
        }

        return null;
    }

    private function normalizePluginList(array $plugins): array
    {
        $rows = [];
        if ($this->isAssocArray($plugins)) {
            if ($this->looksLikePluginEntry($plugins)) {
                $rows[] = $plugins;
            } else {
                foreach ($plugins as $key => $value) {
                    if (is_array($value)) {
                        if (is_string($key) && !array_key_exists('name', $value)) {
                            $value['name'] = $key;
                        }
                        $rows[] = $value;
                    } elseif (is_string($value)) {
                        $rows[] = $value;
                    }
                }
            }
        } else {
            $rows = $plugins;
        }

        $normalized = [];
        foreach ($rows as $row) {
            $plugin = $this->normalizePluginEntry($row);
            if ($plugin !== null) {
                $normalized[] = $plugin;
            }
        }

        return $normalized;
    }

    private function looksLikePluginEntry(array $plugin): bool
    {
        foreach (['name', 'plugin', 'id', 'version', 'ver', 'enabled', 'status', 'online'] as $key) {
            if (array_key_exists($key, $plugin)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePluginEntry($plugin): ?array
    {
        if (is_string($plugin)) {
            $name = trim($plugin);
            if ($name === '') {
                return null;
            }

            return [
                'name' => $name,
                'version' => '--',
                'enabled' => true,
            ];
        }

        if (!is_array($plugin)) {
            return null;
        }

        $nameRaw = $this->firstAvailableValue($plugin, ['name', 'plugin', 'id']);
        $name = trim(is_scalar($nameRaw) ? (string)$nameRaw : '');
        if ($name === '') {
            return null;
        }

        $versionRaw = $this->firstAvailableValue($plugin, ['version', 'ver', 'plugin_version', 'pluginVersion']);
        $version = trim(is_scalar($versionRaw) ? (string)$versionRaw : '');
        if ($version === '') {
            $version = '--';
        }

        $enabledRaw = $this->firstAvailableValue($plugin, ['enabled', 'is_enabled', 'active', 'status', 'online', 'plugin_status']);
        $enabledParsed = $this->parseHealthBool($enabledRaw);
        $enabled = $enabledParsed !== null ? $enabledParsed : true;

        return [
            'name' => $name,
            'version' => $version,
            'enabled' => $enabled,
        ];
    }

    private function readNestedPath(array $payload, array $path)
    {
        $cursor = $payload;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
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
        $offline = [
            'online' => false,
            'players' => ['online' => 0, 'max' => null, 'list' => []],
            'motd' => [
                'source' => 'fallback',
                'format' => 'plain',
                'clean' => '',
                'plain' => '',
                'html' => '',
                'miniMessage' => '',
                'lines' => [],
            ],
            'version' => '',
            'server' => [
                'online' => false,
                'motd' => [
                    'source' => 'fallback',
                    'format' => 'plain',
                    'clean' => '',
                    'plain' => '',
                    'html' => '',
                    'miniMessage' => '',
                    'lines' => [],
                ],
                'version' => '',
            ],
            'stats' => [
                'onlinePlayers' => 0,
                'maxPlayers' => null,
                'tps' => null,
                'mspt' => null,
                'cpuUsage' => null,
                'memoryUsedMb' => null,
                'memoryMaxMb' => null,
                'uptimeSeconds' => null,
            ],
            'plugins' => [],
            'chat' => [],
            'updatedAt' => null,
            'source' => 'fallback',
        ];

        $normalized = $this->normalizeStatusPayload($offline);
        return is_array($normalized) ? $normalized : $offline;
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
        if (!array_key_exists('motd', $normalized) && array_key_exists('motd', $normalized['server'])) {
            $normalized['motd'] = $normalized['server']['motd'];
        }
        if (!array_key_exists('version', $normalized) && array_key_exists('version', $normalized['server'])) {
            $normalized['version'] = $normalized['server']['version'];
        }

        return $normalized;
    }

    private function isSafeProxyUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(trim((string)($parts['host'] ?? '')));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (!$this->isAllowedSkinProxyHost($host)) {
            return false;
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && ($port <= 0 || $port > 65535)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false) {
            return $this->isPublicIp($host);
        }

        return $this->hostnameResolvesToPublicIp($host);
    }

    private function isAllowedSkinProxyHost(string $host): bool
    {
        $allowedHosts = defined('SKIN_PROXY_ALLOWED_HOSTS') && is_array(SKIN_PROXY_ALLOWED_HOSTS)
            ? SKIN_PROXY_ALLOWED_HOSTS
            : ['textures.minecraft.net'];

        $normalizedHost = strtolower(trim($host));
        if ($normalizedHost === '') {
            return false;
        }

        foreach ($allowedHosts as $allowedHostRaw) {
            $allowedHost = strtolower(trim((string)$allowedHostRaw));
            if ($allowedHost !== '' && hash_equals($allowedHost, $normalizedHost)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeContentType(string $contentType): string
    {
        $parts = explode(';', $contentType, 2);
        return strtolower(trim((string)($parts[0] ?? '')));
    }

    private function detectImageMimeByMagic(string $bytes): ?string
    {
        if (strlen($bytes) >= 8 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }
        if (strlen($bytes) >= 3 && substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
            return 'image/jpeg';
        }
        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    private function readSkinProxyStreamWithLimit(string $url, int $maxBytes): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'protocol_version' => 1.1,
                'header' => "Connection: close\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if (!is_resource($stream)) {
            return [null, 0, '', false];
        }

        $bytes = '';
        $tooLarge = false;
        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if (!is_string($chunk) || $chunk === '') {
                break;
            }
            $bytes .= $chunk;
            if (strlen($bytes) > $maxBytes) {
                $tooLarge = true;
                break;
            }
        }

        $metadata = stream_get_meta_data($stream);
        fclose($stream);

        $httpCode = 0;
        $contentType = '';
        $headers = $metadata['wrapper_data'] ?? [];
        if (is_array($headers)) {
            foreach ($headers as $headerLine) {
                $line = trim((string)$headerLine);
                if (preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d{3})#i', $line, $matches) === 1) {
                    $httpCode = (int)$matches[1];
                    continue;
                }
                if (stripos($line, 'Content-Type:') === 0) {
                    $contentType = trim((string)substr($line, 13));
                }
            }
        }

        if ($tooLarge) {
            return [null, $httpCode, $contentType, true];
        }

        return [$bytes, $httpCode, $contentType, false];
    }

    private function hostnameResolvesToPublicIp(string $host): bool
    {
        $hasPublicAddress = false;

        $ipv4Records = @gethostbynamel($host);
        if (is_array($ipv4Records)) {
            foreach ($ipv4Records as $ipv4) {
                if (!$this->isPublicIp((string)$ipv4)) {
                    return false;
                }
                $hasPublicAddress = true;
            }
        }

        if (function_exists('dns_get_record')) {
            $ipv6Records = @dns_get_record($host, DNS_AAAA);
            if (is_array($ipv6Records)) {
                foreach ($ipv6Records as $record) {
                    $ipv6 = (string)($record['ipv6'] ?? '');
                    if ($ipv6 === '') {
                        continue;
                    }
                    if (!$this->isPublicIp($ipv6)) {
                        return false;
                    }
                    $hasPublicAddress = true;
                }
            }
        }

        return $hasPublicAddress;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * 图片跨域反向代理（CORS Image Proxy）
     * 用于第三方皮肤站点不返回 CORS 头时，保证 skinview3d 能读取纹理。
     */
    public function proxySkin(): void
    {
        ApiResponse::ensureRequestIdHeader();
        $rawUrl = trim((string)($_GET['url'] ?? ''));
        if ($rawUrl === '' || preg_match('#^https?://#i', $rawUrl) !== 1 || !$this->isSafeProxyUrl($rawUrl)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Invalid skin URL"}';
            return;
        }

        // 1x1 透明 PNG（作为失败兜底，避免前端白屏）
        $allowedMime = ['image/png', 'image/jpeg', 'image/webp'];
        $maxBytes = max(262144, (int)(defined('SKIN_PROXY_MAX_BYTES') ? SKIN_PROXY_MAX_BYTES : 2097152));
        $maxDimension = max(64, (int)(defined('SKIN_PROXY_MAX_DIMENSION') ? SKIN_PROXY_MAX_DIMENSION : 2048));

        $imgBytes = null;
        $httpCode = 0;
        $upstreamContentType = '';
        $tooLarge = false;

        if (function_exists('curl_init')) {
            $buffer = '';
            $ch = curl_init($rawUrl);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_MAXREDIRS => 0,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; skin-proxy/2.0)',
                    CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer, &$tooLarge, $maxBytes): int {
                        $nextLength = strlen($buffer) + strlen($chunk);
                        if ($nextLength > $maxBytes) {
                            $tooLarge = true;
                            return 0;
                        }
                        $buffer .= $chunk;
                        return strlen($chunk);
                    },
                ]);
                if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                }
                if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
                }
                curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $upstreamContentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if (!$tooLarge && $buffer !== '') {
                    $imgBytes = $buffer;
                }
            }
        } else {
            [$streamBytes, $streamCode, $streamContentType, $streamTooLarge] = $this->readSkinProxyStreamWithLimit($rawUrl, $maxBytes);
            $imgBytes = is_string($streamBytes) && $streamBytes !== '' ? $streamBytes : null;
            $httpCode = (int)$streamCode;
            $upstreamContentType = (string)$streamContentType;
            $tooLarge = (bool)$streamTooLarge;
        }

        if ($tooLarge) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Skin payload too large"}';
            return;
        }

        if (!is_string($imgBytes) || $imgBytes === '' || $httpCode !== 200) {
            http_response_code(502);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Skin upstream unavailable"}';
            return;
        }

        $upstreamMime = $this->normalizeContentType($upstreamContentType);
        if (!in_array($upstreamMime, $allowedMime, true)) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Unsupported skin content type"}';
            return;
        }

        $magicMime = $this->detectImageMimeByMagic($imgBytes);
        if ($magicMime === null || !in_array($magicMime, $allowedMime, true)) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Unsupported skin image signature"}';
            return;
        }

        if (!hash_equals($upstreamMime, $magicMime)) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Skin content type mismatch"}';
            return;
        }

        $imageInfo = @getimagesizefromstring($imgBytes);
        if (!is_array($imageInfo)) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Invalid skin image payload"}';
            return;
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0 || $width > $maxDimension || $height > $maxDimension) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Skin image dimensions are out of range"}';
            return;
        }

        $imageMime = $this->normalizeContentType((string)($imageInfo['mime'] ?? $magicMime));
        if (!in_array($imageMime, $allowedMime, true)) {
            http_response_code(415);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Unsupported skin image format"}';
            return;
        }

        header('Content-Type: ' . $imageMime);
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        echo $imgBytes;
        exit;
    }
}
