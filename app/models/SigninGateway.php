<?php
declare(strict_types=1);

namespace Model;

use Core\MinecraftUuid;
use Core\Model;
use PDO;

class SigninGateway extends Model
{
    private const REQUEST_COOLDOWN_SECONDS = 10;
    private const HISTORY_LIMIT_MAX = 30;
    private const KNOWN_STATUSES = [
        'signed',
        'already_signed',
        'player_offline',
        'server_offline',
        'plugin_missing',
        'plugin_missing_litesignin',
        'litesignin_api_failed',
        'bridge_disabled',
        'invalid_request',
        'invalid_payload',
        'invalid_player_uuid',
        'timeout',
        'failed',
        'requested',
        'accepted',
        'unknown',
        'too_frequent',
        'pending_limit_reached',
        'duplicate_request_id',
    ];

    private static bool $schemaReady = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    public function getStatusForUser(int $userId): array
    {
        $user = $this->fetchUserIdentity($userId);
        if ($user === null) {
            return $this->buildAnonymousStatus();
        }

        $isBound = $this->isBoundIdentity($user['mc_username'], $user['mc_uuid']);

        $serverId = $this->signinServerId();
        $today = date('Y-m-d');
        $todayCache = $isBound ? $this->findDailyCache($userId, $serverId, $today) : null;
        $latestCache = $isBound ? ($todayCache ?? $this->findLatestDailyCache($userId, $serverId)) : null;
        $runtime = $this->fetchRuntimeStatus(
            $isBound ? $user['mc_username'] : '',
            $isBound ? $user['mc_uuid'] : ''
        );

        $signedToday = $todayCache !== null && (int)($todayCache['signed_today'] ?? 0) === 1;
        $continuous = (int)($latestCache['continuous'] ?? 0);
        $total = (int)($latestCache['total'] ?? 0);
        $lastSignInAt = $this->nullableString($latestCache['last_signin_at'] ?? null);
        $source = $this->nullableString($latestCache['last_source'] ?? null) ?: 'litesignin_cache_empty';

        return [
            'bound' => $isBound,
            'playerUuid' => $isBound ? $user['mc_uuid'] : null,
            'playerName' => $isBound ? $user['mc_username'] : null,
            'pluginOnline' => $runtime['pluginOnline'],
            'serverOnline' => $runtime['serverOnline'],
            'playerOnline' => $runtime['playerOnline'],
            'signedToday' => $signedToday,
            'continuous' => max(0, $continuous),
            'total' => max(0, $total),
            'lastSignInAt' => $lastSignInAt,
            'source' => $source,
            'serverId' => $serverId,
            'accountStatus' => $user['status'],
        ];
    }

    public function getHistoryForUser(int $userId, int $limit = self::HISTORY_LIMIT_MAX): array
    {
        $limit = max(1, min(self::HISTORY_LIMIT_MAX, $limit));
        $serverId = $this->signinServerId();
        $sql = sprintf(
            'SELECT request_id, sign_date, signed_today, continuous, total, last_signin_at, last_source, updated_at
             FROM stellar_signin_daily_cache
             WHERE website_user_id = :website_user_id
               AND server_id = :server_id
               AND signed_today = 1
             ORDER BY sign_date DESC, updated_at DESC
             LIMIT %d',
            $limit
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':website_user_id' => $userId,
            ':server_id' => $serverId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'requestId' => trim((string)($row['request_id'] ?? '')),
                'signDate' => (string)($row['sign_date'] ?? ''),
                'signedToday' => (int)($row['signed_today'] ?? 0) === 1,
                'continuous' => max(0, (int)($row['continuous'] ?? 0)),
                'total' => max(0, (int)($row['total'] ?? 0)),
                'lastSignInAt' => trim((string)($row['last_signin_at'] ?? '')) ?: null,
                'source' => trim((string)($row['last_source'] ?? '')) ?: 'litesignin_cache',
                'updatedAt' => trim((string)($row['updated_at'] ?? '')),
            ];
        }, $rows);
    }

    public function claimForUser(int $userId, string $ip, string $userAgent): array
    {
        $user = $this->fetchUserIdentity($userId, true);
        if ($user === null) {
            return $this->errorResult(404, 'failed', 'User not found');
        }

        if ($user['status'] !== 'active') {
            return $this->errorResult(403, 'failed', 'Account is not active');
        }

        if (!$this->isBoundIdentity($user['mc_username'], $user['mc_uuid'])) {
            return $this->errorResult(400, 'failed', 'Please bind your Minecraft account first');
        }

        $serverId = $this->signinServerId();
        $today = date('Y-m-d');
        $todayCache = $this->findDailyCache($userId, $serverId, $today);
        if ($todayCache !== null && (int)($todayCache['signed_today'] ?? 0) === 1) {
            return [
                'ok' => true,
                'http_status' => 200,
                'status' => 'already_signed',
                'message' => 'Already signed in today',
                'request_id' => trim((string)($todayCache['request_id'] ?? '')),
                'status_data' => $this->getStatusForUser($userId),
            ];
        }

        $lastRequest = $this->findLatestRequestForUser($userId, $serverId);
        if ($lastRequest !== null) {
            $lastTs = strtotime((string)($lastRequest['created_at'] ?? ''));
            if ($lastTs !== false && $lastTs >= (time() - self::REQUEST_COOLDOWN_SECONDS)) {
                return $this->errorResult(429, 'too_frequent', 'Requests are too frequent, please retry later');
            }
        }

        $runtime = $this->fetchRuntimeStatus($user['mc_username'], $user['mc_uuid']);
        if (!$runtime['serverOnline']) {
            return $this->errorResult(503, 'server_offline', 'Server is currently unavailable');
        }
        if (!$runtime['pluginOnline']) {
            return $this->errorResult(503, 'plugin_missing', 'Sign-in service is unavailable');
        }
        if ($this->requirePlayerOnline() && !$runtime['playerOnline']) {
            return $this->errorResult(409, 'player_offline', 'Please join the server before signing in');
        }

        $requestId = $this->buildRequestId();
        $requestPayload = [
            'type' => 'signin.request',
            'requestId' => $requestId,
            'serverId' => $serverId,
            'payload' => [
                'websiteUserId' => $userId,
                'playerUuid' => $user['mc_uuid'],
                'playerName' => $user['mc_username'],
                'source' => 'web',
                'requireOnline' => $this->requirePlayerOnline(),
            ],
        ];

        $this->createSigninRequest([
            'request_id' => $requestId,
            'website_user_id' => $userId,
            'player_uuid' => $user['mc_uuid'],
            'player_name' => $user['mc_username'],
            'server_id' => $serverId,
            'source' => 'web',
            'status' => 'requested',
            'message' => 'Request queued',
            'result_payload_json' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => $this->cutString($ip, 64),
            'user_agent' => $this->cutString($userAgent, 255),
        ]);

        $dispatch = $this->dispatchInternalPluginCommand($requestPayload);
        if (!$dispatch['ok']) {
            $normalizedFailure = $this->normalizeRealtimeResponse([
                'status' => (string)($dispatch['status'] ?? ''),
                'message' => (string)($dispatch['message'] ?? ''),
                'payload' => isset($dispatch['payload']) && is_array($dispatch['payload']) ? $dispatch['payload'] : [],
                'ok' => false,
            ]);
            $status = $normalizedFailure['status'];
            $message = $normalizedFailure['message'];
            $this->finalizeRequest($requestId, $status, $message, $dispatch['payload'] ?? null, true);
            $code = $this->httpStatusForSigninStatus($status);
            if ($code < 400) {
                $reportedHttp = (int)($dispatch['http_status'] ?? 0);
                if ($reportedHttp >= 400 && $reportedHttp <= 599) {
                    $code = $reportedHttp;
                } else {
                    $code = 500;
                }
            }
            return $this->errorResult($code, $status, $message, $requestId);
        }

        $normalized = $this->normalizeRealtimeResponse($dispatch['payload'] ?? []);
        $status = $normalized['status'];
        $message = $normalized['message'];
        $payload = $normalized['payload'];
        $terminal = $this->isTerminalSigninStatus($status);

        $this->finalizeRequest($requestId, $status, $message, $dispatch['payload'] ?? null, $terminal);
        if (in_array($status, ['signed', 'already_signed'], true)) {
            $this->upsertDailyCache([
                'request_id' => $requestId,
                'website_user_id' => $userId,
                'player_uuid' => $user['mc_uuid'],
                'player_name' => $user['mc_username'],
                'server_id' => $serverId,
                'sign_date' => $this->extractSignDate($payload),
                'signed_today' => true,
                'continuous' => (int)($payload['continuous'] ?? ($payload['streak'] ?? 0)),
                'total' => (int)($payload['total'] ?? 0),
                'last_signin_at' => $this->normalizeDatetime($payload['lastSignInAt'] ?? ($payload['last_signin_at'] ?? null)),
                'last_source' => $this->cutString((string)($payload['source'] ?? 'web'), 32),
                'raw_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        $httpStatus = $this->httpStatusForSigninStatus($status);
        $ok = in_array($status, ['signed', 'already_signed'], true);
        if ($status === 'accepted' || $status === 'requested' || $status === 'unknown') {
            $ok = true;
        }

        return [
            'ok' => $ok,
            'http_status' => $httpStatus,
            'status' => $status,
            'message' => $message,
            'request_id' => $requestId,
            'status_data' => $this->getStatusForUser($userId),
        ];
    }

    public function handleRealtimeCallback(array $body, string $requestIp = ''): array
    {
        $type = strtolower(trim((string)($body['type'] ?? '')));
        if (!in_array($type, ['signin.result', 'signin.updated'], true)) {
            return ['ok' => false, 'http_status' => 400, 'message' => 'Unsupported event type'];
        }

        if ($type === 'signin.updated') {
            $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : [];
            $userId = (int)($payload['websiteUserId'] ?? $payload['website_user_id'] ?? 0);
            $playerUuid = $this->normalizePlayerUuid((string)($payload['playerUuid'] ?? $payload['player_uuid'] ?? ''));
            $playerName = trim((string)($payload['playerName'] ?? $payload['player_name'] ?? ''));
            $serverId = $this->cutString((string)($payload['serverId'] ?? $this->signinServerId()), 64) ?: $this->signinServerId();

            if ($userId <= 0 && $playerUuid !== '') {
                $linked = $this->findUserIdByPlayerUuid($playerUuid);
                $userId = $linked > 0 ? $linked : 0;
            }
            if ($playerUuid === '' || $playerName === '') {
                return ['ok' => false, 'http_status' => 400, 'message' => 'Invalid signin.updated payload'];
            }
            if ($userId <= 0) {
                return ['ok' => false, 'http_status' => 404, 'message' => 'Website user not found for signin.updated payload'];
            }

            $this->upsertDailyCache([
                'request_id' => $this->cutString((string)($body['requestId'] ?? ''), 80),
                'website_user_id' => $userId,
                'player_uuid' => $playerUuid,
                'player_name' => $playerName,
                'server_id' => $serverId,
                'sign_date' => $this->extractSignDate($payload),
                'signed_today' => $this->parseBool($payload['signedToday'] ?? true),
                'continuous' => (int)($payload['continuous'] ?? ($payload['streak'] ?? 0)),
                'total' => (int)($payload['total'] ?? 0),
                'last_signin_at' => $this->normalizeDatetime($payload['lastSignInAt'] ?? ($payload['last_signin_at'] ?? null)),
                'last_source' => $this->cutString((string)($payload['source'] ?? 'game'), 32),
                'raw_payload_json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return ['ok' => true, 'http_status' => 200, 'message' => 'accepted'];
        }

        $requestId = $this->cutString((string)($body['requestId'] ?? $body['request_id'] ?? ''), 80);
        if ($requestId === '') {
            return ['ok' => false, 'http_status' => 400, 'message' => 'Missing requestId'];
        }

        $result = $this->normalizeRealtimeResponse($body);
        $status = $result['status'];
        $message = $result['message'];
        $payload = $result['payload'];

        $requestRow = $this->findRequestByRequestId($requestId);
        $userId = (int)($payload['websiteUserId'] ?? ($requestRow['website_user_id'] ?? 0));
        $playerUuid = $this->normalizePlayerUuid((string)($payload['playerUuid'] ?? ($requestRow['player_uuid'] ?? '')));
        $playerName = trim((string)($payload['playerName'] ?? ($requestRow['player_name'] ?? '')));
        $serverId = $this->cutString((string)($payload['serverId'] ?? ($requestRow['server_id'] ?? $this->signinServerId())), 64);
        if ($serverId === '') {
            $serverId = $this->signinServerId();
        }

        $terminal = $this->isTerminalSigninStatus($status);
        $this->finalizeRequest($requestId, $status, $message, $body, $terminal);

        if ($userId > 0 && $playerUuid !== '' && $playerName !== '') {
            $signedToday = in_array($status, ['signed', 'already_signed'], true);
            if (array_key_exists('signedToday', $payload)) {
                $signedToday = $this->parseBool($payload['signedToday']) ?? $signedToday;
            }

            $this->upsertDailyCache([
                'request_id' => $requestId,
                'website_user_id' => $userId,
                'player_uuid' => $playerUuid,
                'player_name' => $playerName,
                'server_id' => $serverId,
                'sign_date' => $this->extractSignDate($payload),
                'signed_today' => $signedToday,
                'continuous' => (int)($payload['continuous'] ?? ($payload['streak'] ?? 0)),
                'total' => (int)($payload['total'] ?? 0),
                'last_signin_at' => $this->normalizeDatetime($payload['lastSignInAt'] ?? ($payload['last_signin_at'] ?? null)),
                'last_source' => $this->cutString((string)($payload['source'] ?? 'web'), 32),
                'raw_payload_json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        return ['ok' => true, 'http_status' => 200, 'message' => 'accepted'];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS stellar_signin_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(80) NOT NULL,
                website_user_id INT UNSIGNED NOT NULL,
                player_uuid VARCHAR(64) NOT NULL,
                player_name VARCHAR(64) NOT NULL,
                server_id VARCHAR(64) NOT NULL,
                source VARCHAR(32) NOT NULL DEFAULT 'web',
                status VARCHAR(32) NOT NULL DEFAULT 'requested',
                message VARCHAR(255) NOT NULL DEFAULT '',
                result_payload_json LONGTEXT NULL,
                ip VARCHAR(64) NULL DEFAULT NULL,
                user_agent VARCHAR(255) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                completed_at DATETIME NULL DEFAULT NULL,
                UNIQUE KEY uq_stellar_signin_requests_request_id (request_id),
                KEY idx_stellar_signin_requests_user_created (website_user_id, created_at),
                KEY idx_stellar_signin_requests_status_created (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS stellar_signin_daily_cache (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(80) NULL DEFAULT NULL,
                player_uuid VARCHAR(64) NOT NULL,
                player_name VARCHAR(64) NOT NULL,
                website_user_id INT UNSIGNED NOT NULL,
                server_id VARCHAR(64) NOT NULL,
                sign_date DATE NOT NULL,
                signed_today TINYINT(1) NOT NULL DEFAULT 0,
                continuous INT UNSIGNED NOT NULL DEFAULT 0,
                total INT UNSIGNED NOT NULL DEFAULT 0,
                last_signin_at DATETIME NULL DEFAULT NULL,
                last_source VARCHAR(32) NOT NULL DEFAULT 'litesignin_cache',
                raw_payload_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_stellar_signin_daily_cache_identity (player_uuid, server_id, sign_date),
                KEY idx_stellar_signin_daily_cache_user (website_user_id),
                KEY idx_stellar_signin_daily_cache_request_id (request_id),
                KEY idx_stellar_signin_daily_cache_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$schemaReady = true;
    }

    private function fetchUserIdentity(int $userId, bool $forUpdate = false): ?array
    {
        $sql = '
            SELECT id, status, mc_username, mc_uuid
            FROM users
            WHERE id = :id
            LIMIT 1
        ';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $mcUsername = trim((string)($row['mc_username'] ?? ''));
        $mcUuid = $this->normalizePlayerUuid((string)($row['mc_uuid'] ?? ''));

        return [
            'id' => (int)$row['id'],
            'status' => trim((string)($row['status'] ?? 'active')),
            'mc_username' => $mcUsername,
            'mc_uuid' => $mcUuid,
        ];
    }

    private function buildAnonymousStatus(): array
    {
        return [
            'bound' => false,
            'playerUuid' => null,
            'playerName' => null,
            'pluginOnline' => false,
            'serverOnline' => false,
            'playerOnline' => false,
            'signedToday' => false,
            'continuous' => 0,
            'total' => 0,
            'lastSignInAt' => null,
            'source' => 'litesignin_cache_empty',
            'serverId' => $this->signinServerId(),
            'accountStatus' => 'guest',
        ];
    }

    private function fetchRuntimeStatus(string $playerName, string $playerUuid = ''): array
    {
        $statusPayload = $this->fetchWsJson('/api/status', true);
        $healthPayload = $this->fetchWsJson('/health', true);

        $statusNode = $statusPayload;
        $healthNode = $healthPayload;

        $serverOnline = $this->resolveServerOnline($statusNode);
        $playerOnline = $this->resolvePlayerOnline($statusNode, $playerName, $playerUuid);
        $pluginOnline = $this->resolveLiteSignInOnline($statusNode, $healthNode);

        return [
            'serverOnline' => $serverOnline,
            'pluginOnline' => $pluginOnline,
            'playerOnline' => $playerOnline,
        ];
    }

    private function resolveServerOnline(array $statusNode): bool
    {
        $server = isset($statusNode['server']) && is_array($statusNode['server']) ? $statusNode['server'] : [];
        $serverOnline = $this->parseBool($server['online'] ?? null);
        if ($serverOnline !== null) {
            return $serverOnline;
        }

        $globalOnline = $this->parseBool($statusNode['online'] ?? null);
        if ($globalOnline !== null) {
            return $globalOnline;
        }

        $players = $this->extractPlayersList($statusNode);
        return $players !== [];
    }

    private function resolvePlayerOnline(array $statusNode, string $playerName, string $playerUuid = ''): bool
    {
        $needleUuid = $this->normalizePlayerUuidFlat($playerUuid);
        $needleName = strtolower(trim($playerName));
        if ($needleUuid === '' && $needleName === '') {
            return false;
        }

        foreach ($this->extractPlayersList($statusNode) as $row) {
            if (is_array($row)) {
                $rowUuid = $this->normalizePlayerUuidFlat((string)($row['uuid'] ?? ($row['id'] ?? '')));
                if ($needleUuid !== '' && $rowUuid !== '' && $rowUuid === $needleUuid) {
                    return true;
                }

                $name = trim((string)($row['name_clean'] ?? ($row['name'] ?? ($row['username'] ?? ''))));
                if ($needleName !== '' && $name !== '' && strtolower($name) === $needleName) {
                    return true;
                }

                continue;
            }

            if (is_string($row) && $needleName !== '' && strtolower(trim($row)) === $needleName) {
                return true;
            }
        }

        return false;
    }

    private function resolveLiteSignInOnline(array $statusNode, array $healthNode): bool
    {
        $pluginState = $this->extractLiteSignInState($statusNode);
        if ($pluginState !== null) {
            return $pluginState;
        }

        $healthState = $this->parseBool(
            $healthNode['plugin_online']
            ?? $healthNode['pluginOnline']
            ?? $healthNode['plugin']
            ?? null
        );
        if ($healthState !== null) {
            return $healthState;
        }

        return false;
    }

    private function extractLiteSignInState(array $statusNode): ?bool
    {
        $plugins = $statusNode['plugins'] ?? [];
        if (!is_array($plugins)) {
            return null;
        }

        $rows = [];
        if ($this->isAssocArray($plugins)) {
            foreach ($plugins as $key => $value) {
                if (is_array($value)) {
                    if (!isset($value['name']) && is_string($key)) {
                        $value['name'] = $key;
                    }
                    $rows[] = $value;
                } elseif (is_string($value)) {
                    $rows[] = ['name' => $value, 'enabled' => true];
                }
            }
        } else {
            $rows = $plugins;
        }

        foreach ($rows as $plugin) {
            if (is_string($plugin)) {
                if (stripos($plugin, 'litesignin') !== false) {
                    return true;
                }
                continue;
            }
            if (!is_array($plugin)) {
                continue;
            }

            $name = trim((string)($plugin['name'] ?? ($plugin['plugin'] ?? ($plugin['id'] ?? ''))));
            if ($name === '' || stripos($name, 'litesignin') === false) {
                continue;
            }

            $enabled = $this->parseBool($plugin['enabled'] ?? ($plugin['online'] ?? ($plugin['status'] ?? null)));
            return $enabled ?? true;
        }

        return null;
    }

    private function extractPlayersList(array $statusNode): array
    {
        $playersNode = $statusNode['players'] ?? null;
        if (is_array($playersNode)) {
            if (!$this->isAssocArray($playersNode)) {
                return $playersNode;
            }
            if (isset($playersNode['list']) && is_array($playersNode['list'])) {
                return $playersNode['list'];
            }
            if (isset($playersNode['sample']) && is_array($playersNode['sample'])) {
                return $playersNode['sample'];
            }
            if (isset($playersNode['players']) && is_array($playersNode['players']) && !$this->isAssocArray($playersNode['players'])) {
                return $playersNode['players'];
            }
        }

        if (isset($statusNode['playerList']) && is_array($statusNode['playerList']) && !$this->isAssocArray($statusNode['playerList'])) {
            return $statusNode['playerList'];
        }
        if (isset($statusNode['list']) && is_array($statusNode['list']) && !$this->isAssocArray($statusNode['list'])) {
            return $statusNode['list'];
        }

        $dataNode = $statusNode['data'] ?? null;
        if (is_array($dataNode)) {
            $dataPlayers = $dataNode['players'] ?? null;
            if (is_array($dataPlayers)) {
                if (!$this->isAssocArray($dataPlayers)) {
                    return $dataPlayers;
                }
                if (isset($dataPlayers['list']) && is_array($dataPlayers['list'])) {
                    return $dataPlayers['list'];
                }
                if (isset($dataPlayers['sample']) && is_array($dataPlayers['sample'])) {
                    return $dataPlayers['sample'];
                }
            }
            if (isset($dataNode['playerList']) && is_array($dataNode['playerList']) && !$this->isAssocArray($dataNode['playerList'])) {
                return $dataNode['playerList'];
            }
        }

        return [];
    }

    private function fetchWsJson(string $path, bool $withToken): array
    {
        $baseUrl = defined('WS_STATUS_API_BASE') ? trim((string)WS_STATUS_API_BASE) : 'http://127.0.0.1:3001';
        if ($baseUrl === '') {
            $baseUrl = 'http://127.0.0.1:3001';
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $token = defined('WS_STATUS_API_TOKEN') ? trim((string)WS_STATUS_API_TOKEN) : '';
        $headers = ['Accept: application/json'];
        if ($withToken && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
            $headers[] = 'x-api-token: ' . $token;
        }

        $timeoutMs = defined('WS_STATUS_API_TIMEOUT_MS') ? (int)WS_STATUS_API_TIMEOUT_MS : 2500;
        $timeoutMs = max(500, $timeoutMs);
        $result = $this->httpGetJson($url, $headers, $timeoutMs);
        return $this->unwrapPayload($result);
    }

    private function dispatchInternalPluginCommand(array $payload): array
    {
        $baseUrl = defined('REALTIME_INTERNAL_URL') ? trim((string)REALTIME_INTERNAL_URL) : '';
        if ($baseUrl === '') {
            return ['ok' => false, 'status' => 'failed', 'message' => 'REALTIME_INTERNAL_URL is not configured'];
        }

        $secret = defined('REALTIME_INTERNAL_SECRET') ? trim((string)REALTIME_INTERNAL_SECRET) : '';
        if ($secret === '') {
            return ['ok' => false, 'status' => 'failed', 'message' => 'REALTIME internal secret is not configured'];
        }

        $timeoutMs = defined('SIGNIN_REQUEST_TIMEOUT_MS') ? (int)SIGNIN_REQUEST_TIMEOUT_MS : 5000;
        $timeoutMs = max(500, $timeoutMs);
        $url = rtrim($baseUrl, '/') . '/internal/plugin-command';
        $headers = [
            'Content-Type: application/json',
            'X-Stellar-Realtime-Secret: ' . str_replace(["\r", "\n"], '', $secret),
        ];

        return $this->httpPostJson($url, $payload, $headers, $timeoutMs);
    }

    private function httpGetJson(string $url, array $headers, int $timeoutMs): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
                    CURLOPT_TIMEOUT_MS => $timeoutMs,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                $response = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $errorNo = (int)curl_errno($ch);
                curl_close($ch);

                if ($response === false || $errorNo !== 0) {
                    return [];
                }
                if ($httpCode < 200 || $httpCode >= 300) {
                    return [];
                }
                $decoded = json_decode((string)$response, true);
                return is_array($decoded) ? $decoded : [];
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => max(0.5, $timeoutMs / 1000),
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || trim($response) === '') {
            return [];
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function httpPostJson(string $url, array $payload, array $headers, int $timeoutMs): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'JSON encode failed', 'http_status' => 500];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
                    CURLOPT_TIMEOUT_MS => $timeoutMs,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                $response = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $errorNo = (int)curl_errno($ch);
                curl_close($ch);

                $decoded = json_decode((string)$response, true);
                $decodedArr = is_array($decoded) ? $decoded : [];
                $normalized = $this->normalizeRealtimeResponse($decodedArr);

                if ($response === false) {
                    return [
                        'ok' => false,
                        'status' => $errorNo === 28 ? 'timeout' : 'failed',
                        'message' => $errorNo === 28 ? 'Sign-in request timed out' : 'Internal request failed',
                        'payload' => $decodedArr,
                        'http_status' => $errorNo === 28 ? 504 : ($httpCode > 0 ? $httpCode : 500),
                    ];
                }

                if ($errorNo !== 0) {
                    $status = $errorNo === 28 ? 'timeout' : $normalized['status'];
                    if ($status === 'unknown') {
                        $status = $errorNo === 28 ? 'timeout' : 'failed';
                    }
                    $message = trim($normalized['message']);
                    if ($message === '') {
                        $message = $errorNo === 28 ? 'Sign-in request timed out' : 'Internal request failed';
                    }
                    return [
                        'ok' => false,
                        'status' => $status,
                        'message' => $message,
                        'payload' => $decodedArr,
                        'http_status' => $errorNo === 28 ? 504 : ($httpCode > 0 ? $httpCode : 500),
                    ];
                }
                if ($httpCode < 200 || $httpCode >= 300) {
                    $status = $normalized['status'];
                    if ($status === 'unknown') {
                        $status = $httpCode === 504 ? 'timeout' : 'failed';
                    }
                    $message = trim($normalized['message']);
                    if ($message === '') {
                        $message = $httpCode === 504 ? 'Sign-in request timed out' : 'Internal request rejected';
                    }
                    return [
                        'ok' => false,
                        'status' => $status,
                        'message' => $message,
                        'payload' => $decodedArr,
                        'http_status' => $httpCode,
                    ];
                }

                $status = $normalized['status'];
                if ($status === 'unknown' && $decodedArr === []) {
                    $status = 'accepted';
                }
                $message = trim($normalized['message']);
                if ($message === '') {
                    $message = $status === 'accepted' ? 'Accepted' : $this->defaultMessageForStatus($status);
                }

                return [
                    'ok' => true,
                    'status' => $status,
                    'message' => $message,
                    'payload' => $decodedArr,
                    'http_status' => $httpCode > 0 ? $httpCode : 200,
                ];
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => max(0.5, $timeoutMs / 1000),
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        $httpCode = $this->extractHttpResponseCodeFromHeaders($responseHeaders);
        if (!is_string($response)) {
            return [
                'ok' => false,
                'status' => $httpCode === 504 ? 'timeout' : 'failed',
                'message' => $httpCode === 504 ? 'Sign-in request timed out' : 'Internal request failed',
                'http_status' => $httpCode > 0 ? $httpCode : 500,
            ];
        }

        $decoded = json_decode($response, true);
        $decodedArr = is_array($decoded) ? $decoded : [];
        $normalized = $this->normalizeRealtimeResponse($decodedArr);

        if ($httpCode > 0 && ($httpCode < 200 || $httpCode >= 300)) {
            $status = $normalized['status'];
            if ($status === 'unknown') {
                $status = $httpCode === 504 ? 'timeout' : 'failed';
            }
            $message = trim($normalized['message']);
            if ($message === '') {
                $message = $httpCode === 504 ? 'Sign-in request timed out' : 'Internal request rejected';
            }

            return [
                'ok' => false,
                'status' => $status,
                'message' => $message,
                'payload' => $decodedArr,
                'http_status' => $httpCode,
            ];
        }

        $status = $normalized['status'];
        if ($status === 'unknown' && $decodedArr === []) {
            $status = 'accepted';
        }
        $message = trim($normalized['message']);
        if ($message === '') {
            $message = $status === 'accepted' ? 'Accepted' : $this->defaultMessageForStatus($status);
        }

        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
            'payload' => $decodedArr,
            'http_status' => $httpCode > 0 ? $httpCode : 200,
        ];
    }

    private function extractHttpResponseCodeFromHeaders(array $headers): int
    {
        foreach ($headers as $headerLine) {
            $line = trim((string)$headerLine);
            if (preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d{3})#i', $line, $matches) === 1) {
                return (int)$matches[1];
            }
        }

        return 0;
    }

    private function unwrapPayload(array $payload): array
    {
        $candidate = $payload;
        if (isset($candidate['data']) && is_array($candidate['data'])) {
            $candidate = $candidate['data'];
        }
        if (isset($candidate['payload']) && is_array($candidate['payload'])) {
            $candidate = $candidate['payload'];
        }
        if (isset($candidate['status']) && is_array($candidate['status'])) {
            $candidate = $candidate['status'];
        }

        return is_array($candidate) ? $candidate : [];
    }

    private function normalizeRealtimeResponse(array $raw): array
    {
        $candidate = $this->unwrapPayload($raw);
        $status = strtolower(trim((string)($candidate['status'] ?? ($raw['status'] ?? ''))));
        if ($status === '') {
            $status = strtolower(trim((string)($candidate['result'] ?? ($raw['result'] ?? ''))));
        }
        if ($status === '') {
            $ok = $this->parseBool($candidate['ok'] ?? ($raw['ok'] ?? null));
            if ($ok === true) {
                $status = 'accepted';
            } elseif ($ok === false) {
                $status = 'failed';
            }
        }

        if (!in_array($status, self::KNOWN_STATUSES, true)) {
            $status = 'unknown';
        }

        $payload = [];
        if (isset($candidate['payload']) && is_array($candidate['payload'])) {
            $payload = $candidate['payload'];
        } elseif (isset($raw['payload']) && is_array($raw['payload'])) {
            $payload = $raw['payload'];
        } else {
            $payload = $candidate;
        }

        $message = trim((string)($candidate['message'] ?? ($raw['message'] ?? '')));
        if ($message === '') {
            $message = $this->defaultMessageForStatus($status);
        }

        return [
            'status' => $status,
            'message' => $message,
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    private function defaultMessageForStatus(string $status): string
    {
        return match ($status) {
            'signed' => 'Signed in successfully',
            'already_signed' => 'Already signed in today',
            'player_offline' => 'Please join the server before signing in',
            'server_offline' => 'Server is currently unavailable',
            'plugin_missing' => 'Sign-in service is unavailable',
            'plugin_missing_litesignin' => 'LiteSignIn plugin is missing or disabled',
            'litesignin_api_failed' => 'LiteSignIn API execution failed',
            'bridge_disabled' => 'Website sign-in bridge is disabled',
            'invalid_request' => 'Invalid sign-in request',
            'invalid_payload' => 'Invalid sign-in payload',
            'invalid_player_uuid' => 'Invalid player UUID',
            'timeout' => 'Sign-in request timed out',
            'too_frequent' => 'Requests are too frequent, please retry later',
            'pending_limit_reached' => 'Too many pending requests, please retry later',
            'duplicate_request_id' => 'Duplicate request id',
            'accepted', 'requested', 'unknown' => 'Request accepted and processing',
            default => 'Sign-in failed, please retry later',
        };
    }

    private function defaultSource(): string
    {
        return 'litesignin_cache';
    }

    private function httpStatusForSigninStatus(string $status): int
    {
        return match ($status) {
            'signed', 'already_signed', 'accepted', 'requested', 'unknown' => 200,
            'player_offline' => 409,
            'server_offline', 'plugin_missing', 'plugin_missing_litesignin', 'bridge_disabled' => 503,
            'litesignin_api_failed' => 502,
            'invalid_request', 'invalid_payload', 'invalid_player_uuid' => 400,
            'duplicate_request_id' => 409,
            'timeout' => 504,
            'too_frequent', 'pending_limit_reached' => 429,
            default => 500,
        };
    }

    private function isTerminalSigninStatus(string $status): bool
    {
        return !in_array($status, ['requested', 'accepted', 'unknown'], true);
    }

    private function createSigninRequest(array $row): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO stellar_signin_requests (
                request_id,
                website_user_id,
                player_uuid,
                player_name,
                server_id,
                source,
                status,
                message,
                result_payload_json,
                ip,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :request_id,
                :website_user_id,
                :player_uuid,
                :player_name,
                :server_id,
                :source,
                :status,
                :message,
                :result_payload_json,
                :ip,
                :user_agent,
                NOW(),
                NOW()
            )
        ');

        $stmt->execute([
            ':request_id' => (string)($row['request_id'] ?? ''),
            ':website_user_id' => (int)($row['website_user_id'] ?? 0),
            ':player_uuid' => (string)($row['player_uuid'] ?? ''),
            ':player_name' => (string)($row['player_name'] ?? ''),
            ':server_id' => (string)($row['server_id'] ?? $this->signinServerId()),
            ':source' => (string)($row['source'] ?? 'web'),
            ':status' => (string)($row['status'] ?? 'requested'),
            ':message' => (string)($row['message'] ?? ''),
            ':result_payload_json' => (string)($row['result_payload_json'] ?? ''),
            ':ip' => $this->nullableString($row['ip'] ?? null),
            ':user_agent' => $this->nullableString($row['user_agent'] ?? null),
        ]);
    }

    private function findLatestRequestForUser(int $userId, string $serverId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM stellar_signin_requests
            WHERE website_user_id = :website_user_id
              AND server_id = :server_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':website_user_id' => $userId,
            ':server_id' => $serverId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findRequestByRequestId(string $requestId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM stellar_signin_requests
            WHERE request_id = :request_id
            LIMIT 1
        ');
        $stmt->execute([':request_id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function finalizeRequest(string $requestId, string $status, string $message, ?array $resultPayload, bool $terminal): void
    {
        $safeStatus = in_array($status, self::KNOWN_STATUSES, true) ? $status : 'unknown';
        $json = '';
        if ($resultPayload !== null) {
            $encoded = json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $json = is_string($encoded) ? $encoded : '';
        }

        if ($terminal) {
            $stmt = $this->db->prepare('
                UPDATE stellar_signin_requests
                SET status = :status,
                    message = :message,
                    result_payload_json = :result_payload_json,
                    updated_at = NOW(),
                    completed_at = NOW()
                WHERE request_id = :request_id
                LIMIT 1
            ');
            $stmt->execute([
                ':status' => $safeStatus,
                ':message' => $this->cutString($message, 255),
                ':result_payload_json' => $json,
                ':request_id' => $requestId,
            ]);
            return;
        }

        $stmt = $this->db->prepare('
            UPDATE stellar_signin_requests
            SET status = :status,
                message = :message,
                result_payload_json = :result_payload_json,
                updated_at = NOW()
            WHERE request_id = :request_id
            LIMIT 1
        ');
        $stmt->execute([
            ':status' => $safeStatus,
            ':message' => $this->cutString($message, 255),
            ':result_payload_json' => $json,
            ':request_id' => $requestId,
        ]);
    }

    private function findDailyCache(int $userId, string $serverId, string $signDate): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM stellar_signin_daily_cache
            WHERE website_user_id = :website_user_id
              AND server_id = :server_id
              AND sign_date = :sign_date
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':website_user_id' => $userId,
            ':server_id' => $serverId,
            ':sign_date' => $signDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findLatestDailyCache(int $userId, string $serverId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
            FROM stellar_signin_daily_cache
            WHERE website_user_id = :website_user_id
              AND server_id = :server_id
            ORDER BY sign_date DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':website_user_id' => $userId,
            ':server_id' => $serverId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function upsertDailyCache(array $row): void
    {
        $signDate = $this->extractSignDate($row);
        $lastSignInAt = $this->normalizeDatetime($row['last_signin_at'] ?? null);
        if ($lastSignInAt === null && ($this->parseBool($row['signed_today'] ?? false) ?? false)) {
            $lastSignInAt = date('Y-m-d H:i:s');
        }

        $stmt = $this->db->prepare('
            INSERT INTO stellar_signin_daily_cache (
                request_id,
                player_uuid,
                player_name,
                website_user_id,
                server_id,
                sign_date,
                signed_today,
                continuous,
                total,
                last_signin_at,
                last_source,
                raw_payload_json,
                created_at,
                updated_at
            ) VALUES (
                :request_id,
                :player_uuid,
                :player_name,
                :website_user_id,
                :server_id,
                :sign_date,
                :signed_today,
                :continuous,
                :total,
                :last_signin_at,
                :last_source,
                :raw_payload_json,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                player_name = VALUES(player_name),
                website_user_id = VALUES(website_user_id),
                signed_today = VALUES(signed_today),
                continuous = VALUES(continuous),
                total = VALUES(total),
                last_signin_at = VALUES(last_signin_at),
                last_source = VALUES(last_source),
                raw_payload_json = VALUES(raw_payload_json),
                updated_at = NOW()
        ');
        $stmt->execute([
            ':request_id' => $this->nullableString($row['request_id'] ?? null),
            ':player_uuid' => $this->normalizePlayerUuid((string)($row['player_uuid'] ?? '')),
            ':player_name' => $this->cutString((string)($row['player_name'] ?? ''), 64),
            ':website_user_id' => (int)($row['website_user_id'] ?? 0),
            ':server_id' => $this->cutString((string)($row['server_id'] ?? $this->signinServerId()), 64),
            ':sign_date' => $signDate,
            ':signed_today' => ($this->parseBool($row['signed_today'] ?? false) ?? false) ? 1 : 0,
            ':continuous' => max(0, (int)($row['continuous'] ?? 0)),
            ':total' => max(0, (int)($row['total'] ?? 0)),
            ':last_signin_at' => $lastSignInAt,
            ':last_source' => $this->cutString((string)($row['last_source'] ?? $this->defaultSource()), 32),
            ':raw_payload_json' => $this->nullableString($row['raw_payload_json'] ?? null),
        ]);
    }

    private function findUserIdByPlayerUuid(string $uuid): int
    {
        $flat = $this->normalizePlayerUuidFlat($uuid);
        if ($flat === '') {
            return 0;
        }

        $stmt = $this->db->prepare('
            SELECT id
            FROM users
            WHERE REPLACE(LOWER(mc_uuid), "-", "") = :flat_uuid
            LIMIT 1
        ');
        $stmt->execute([':flat_uuid' => $flat]);
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int)$value : 0;
    }

    private function parseBool($value): ?bool
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

        $text = strtolower(trim($value));
        if ($text === '') {
            return null;
        }
        if (in_array($text, ['1', 'true', 'yes', 'on', 'ok', 'online', 'up', 'enabled'], true)) {
            return true;
        }
        if (in_array($text, ['0', 'false', 'no', 'off', 'error', 'offline', 'down', 'disabled'], true)) {
            return false;
        }

        return null;
    }

    private function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function buildRequestId(): string
    {
        return 'signin_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
    }

    private function normalizePlayerUuid(string $uuid): string
    {
        return MinecraftUuid::normalizeToDashed($uuid);
    }

    private function normalizePlayerUuidFlat(string $uuid): string
    {
        return str_replace('-', '', $this->normalizePlayerUuid($uuid));
    }

    private function isBoundIdentity(string $playerName, string $playerUuid): bool
    {
        if ($playerName === '' || $playerUuid === '') {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_]{1,16}$/', $playerName) === 1;
    }

    private function extractSignDate($payload): string
    {
        $raw = null;
        if (is_array($payload)) {
            $raw = $payload['signDate'] ?? ($payload['sign_date'] ?? null);
        }
        $text = trim((string)$raw);
        if ($text !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            return $text;
        }

        return date('Y-m-d');
    }

    private function normalizeDatetime($raw): ?string
    {
        $text = trim((string)$raw);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $text) === 1) {
            return $text;
        }

        if (is_numeric($raw)) {
            $num = (int)$raw;
            if ($num > 1000000000000) {
                $num = (int)floor($num / 1000);
            }
            if ($num > 0) {
                return date('Y-m-d H:i:s', $num);
            }
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function cutString(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $max);
    }

    private function signinServerId(): string
    {
        $value = defined('SIGNIN_SERVER_ID') ? trim((string)SIGNIN_SERVER_ID) : '';
        return $value !== '' ? $value : 'survival-1';
    }

    private function requirePlayerOnline(): bool
    {
        if (defined('SIGNIN_REQUIRE_PLAYER_ONLINE')) {
            return (bool)SIGNIN_REQUIRE_PLAYER_ONLINE;
        }
        return true;
    }

    private function errorResult(int $httpStatus, string $status, string $message, string $requestId = ''): array
    {
        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'status' => $status,
            'message' => $message,
            'request_id' => $requestId,
        ];
    }
}
