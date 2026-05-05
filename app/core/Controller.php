<?php
declare(strict_types=1);

namespace Core;

use Model\User;

class Controller
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();

        // Remember Me silent auto-login interceptor
        if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_me'])) {
            $cookieVal = (string)$_COOKIE['remember_me'];
            $parts = explode(':', $cookieVal, 2);
            if (count($parts) === 2) {
                $selector = trim((string)($parts[0] ?? ''));
                $validator = trim((string)($parts[1] ?? ''));

                // selector: 12 bytes => 24 hex chars
                // validator: 32 bytes => 64 hex chars
                $selectorOk = preg_match('/^[0-9a-fA-F]{24}$/', $selector) === 1;
                $validatorOk = preg_match('/^[0-9a-fA-F]{64}$/', $validator) === 1;

                if ($selectorOk && $validatorOk) {
                    $authed = false;
                    $usersModel = new User();
                    $dbRow = null;

                    try {
                        $dbRow = $usersModel->getRememberToken($selector);
                    } catch (\Throwable $e) {
                        $dbRow = null;
                    }

                    if ($dbRow && !empty($dbRow['expires'])) {
                        $expiresTs = strtotime((string)$dbRow['expires']);
                        $userStatus = (string)($dbRow['status'] ?? '');
                        if ($expiresTs !== false && $expiresTs >= time() && $userStatus === 'active') {
                            // Strict comparison to prevent timing attacks.
                            $computedHash = hash('sha256', $validator);
                            if (!empty($dbRow['validator_hash']) && hash_equals($dbRow['validator_hash'], $computedHash)) {
                                $this->regenerateSessionIdSafely();
                                $_SESSION['user_id'] = (int)$dbRow['user_id'];
                                $_SESSION['username'] = (string)$dbRow['username'];
                                $_SESSION['role'] = (string)$dbRow['role'];
                                $authed = true;
                            }
                        }
                    }

                    if (!$authed) {
                        try {
                            $usersModel->deleteRememberToken($selector);
                        } catch (\Throwable $e) {
                            // ignore cleanup failures
                        }

                        $this->clearRememberMeCookie();
                    }
                }
            }
        }
    }

    protected function render(string $template, array $data = []): string
    {
        return $this->view->render($template, $data);
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $path = $this->getRequestPath();
        if (!$this->isUnifiedApiCandidatePath($path)) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        ApiResponse::ensureRequestIdHeader();

        if (!$this->shouldUseUnifiedApiResponse($path)) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        [$success, $message, $code, $payload] = $this->normalizeUnifiedResponsePayload($data, $status, $path);
        if ($success) {
            echo ApiResponse::success($payload, $message);
            return;
        }

        echo ApiResponse::error($code, $message, $payload);
    }

    protected function isAjaxRequest(): bool
    {
        $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }

    protected function redirectOrJson(string $redirectUrl, array $data = [], string $message = 'ok', int $status = 200): void
    {
        if ($this->isAjaxRequest() || $this->hasAjaxFlag()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            ApiResponse::ensureRequestIdHeader();

            if ($status >= 400) {
                echo ApiResponse::error($this->inferApiCode(false, $status), $message, $data);
            } else {
                echo ApiResponse::success($data, $message);
            }
            exit;
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    private function getRequestPath(): string
    {
        $rawUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($rawUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    private function isUnifiedApiCandidatePath(string $path): bool
    {
        if (preg_match('#^/(api|auth|profile)(/|$)#', $path) === 1) {
            return true;
        }

        return in_array($path, ['/forgot-password', '/reset-password'], true);
    }

    private function shouldUseUnifiedApiResponse(string $path): bool
    {
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        return true;
    }

    private function hasAjaxFlag(): bool
    {
        return array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST);
    }

    /**
     * @return array{0:bool,1:string,2:int,3:array}
     */
    private function normalizeUnifiedResponsePayload(array $data, int $status, string $path): array
    {
        $success = array_key_exists('success', $data) ? (bool)$data['success'] : $status < 400;

        $message = 'ok';
        if (!$success) {
            $message = 'error';
        }
        if (isset($data['message']) && is_scalar($data['message'])) {
            $message = trim((string)$data['message']) !== '' ? (string)$data['message'] : $message;
        }

        $code = isset($data['code']) && is_numeric($data['code'])
            ? (int)$data['code']
            : $this->inferApiCode($success, $status);

        $payload = $data;
        unset($payload['success'], $payload['code'], $payload['message'], $payload['requestId'], $payload['timestamp']);

        if (array_key_exists('data', $payload) && count($payload) === 1 && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        if ($this->shouldNormalizeStatusPayload($path)) {
            $payload = $this->ensureStatusPayloadShape($payload);
        }

        return [$success, $message, $code, $payload];
    }

    private function shouldNormalizeStatusPayload(string $path): bool
    {
        return in_array($path, ['/api/server/status/update', '/api/status', '/api/status/cache', '/api/leaderboard/search'], true);
    }

    private function ensureStatusPayloadShape(array $payload): array
    {
        $normalized = $payload;

        if (!isset($normalized['server']) || !is_array($normalized['server'])) {
            $normalized['server'] = [];
        }
        if (array_key_exists('online', $payload) && !array_key_exists('online', $normalized['server'])) {
            $normalized['server']['online'] = (bool)$payload['online'];
        }
        if (isset($payload['motd']) && !array_key_exists('motd', $normalized['server'])) {
            $normalized['server']['motd'] = is_array($payload['motd']) ? $payload['motd'] : [];
        }
        if (isset($payload['version']) && !array_key_exists('version', $normalized['server'])) {
            $normalized['server']['version'] = is_array($payload['version']) ? $payload['version'] : [];
        }

        if (!isset($normalized['stats']) || !is_array($normalized['stats'])) {
            $normalized['stats'] = [];
        }

        if (!isset($normalized['players']) || !is_array($normalized['players']) || $this->isAssocArray($normalized['players'])) {
            $normalized['players'] = $this->extractPlayersList($payload);
        }

        if (!isset($normalized['plugins']) || !is_array($normalized['plugins'])) {
            $normalized['plugins'] = [];
        }

        if (!isset($normalized['chat']) || !is_array($normalized['chat'])) {
            $normalized['chat'] = [];
        }

        return $normalized;
    }

    private function extractPlayersList(array $payload): array
    {
        if (isset($payload['players']) && is_array($payload['players'])) {
            $players = $payload['players'];
            if (isset($players['list']) && is_array($players['list'])) {
                return array_values($players['list']);
            }
            if (!$this->isAssocArray($players)) {
                return array_values($players);
            }
        }

        if (isset($payload['results']) && is_array($payload['results'])) {
            return array_values($payload['results']);
        }

        return [];
    }

    private function isAssocArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function inferApiCode(bool $success, int $status): int
    {
        if ($success) {
            return ApiCode::SUCCESS;
        }

        if ($status === 401 || $status === 403) {
            return ApiCode::AUTH_INVALID;
        }

        if ($status === 404) {
            return ApiCode::USER_NOT_FOUND;
        }

        return ApiCode::SERVER_ERROR;
    }

    protected function regenerateSessionIdSafely(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        try {
            @session_regenerate_id(true);
        } catch (\Throwable $e) {
        }
    }

    protected function clearRememberMeCookie(): void
    {
        setcookie('remember_me', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    protected function generateCsrfToken(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function validateCsrfToken(): void
    {
        $this->generateCsrfToken();

        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $this->json(['success' => false, 'message' => 'CSRF token mismatch'], 403);
            exit;
        }
    }

    /**
     * 传统表单 POST（后台管理等）：校验失败重定向，不输出 JSON。
     */
    protected function validateCsrfForFormPost(string $redirectUrl = '/admin'): void
    {
        $this->generateCsrfToken();
        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            header('Location: ' . $redirectUrl . $sep . 'err=csrf');
            exit;
        }
    }

    protected function getClientIp(): string
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr === '' || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return '0.0.0.0';
        }

        // Only trust forwarding headers when request comes from configured trusted proxy.
        if (!$this->isTrustedProxyIp($remoteAddr)) {
            return $remoteAddr;
        }

        $cfIp = $this->normalizeForwardedHeaderIp((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cfIp !== null) {
            return $cfIp;
        }

        $realIp = $this->normalizeForwardedHeaderIp((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($realIp !== null) {
            return $realIp;
        }

        $forwardedFor = $this->extractClientIpFromXForwardedFor((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== null) {
            return $forwardedFor;
        }

        return $remoteAddr;
    }

    private function normalizeForwardedHeaderIp(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $value;
    }

    private function extractClientIpFromXForwardedFor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $candidates = array_filter(array_map('trim', explode(',', $value)), static function ($item): bool {
            return is_string($item) && $item !== '';
        });

        $validIps = [];
        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $validIps[] = $candidate;
        }

        if ($validIps === []) {
            return null;
        }

        // Prefer first public IP in chain to reduce spoofing risk from private/reserved ranges.
        foreach ($validIps as $ip) {
            if ($this->isPublicIp($ip)) {
                return $ip;
            }
        }

        return $validIps[0];
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function isTrustedProxyIp(string $ip): bool
    {
        $entries = $this->getTrustedProxyEntries();
        if ($entries === []) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($this->ipMatchesCidrRule($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getTrustedProxyEntries(): array
    {
        if (!defined('TRUSTED_PROXIES') || !is_array(TRUSTED_PROXIES)) {
            return [];
        }

        $result = [];
        foreach (TRUSTED_PROXIES as $entry) {
            $value = trim((string)$entry);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function ipMatchesCidrRule(string $clientIp, string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') {
            return false;
        }

        if (strpos($rule, '/') === false) {
            return filter_var($rule, FILTER_VALIDATE_IP) !== false && strcasecmp($clientIp, $rule) === 0;
        }

        [$network, $prefixRaw] = array_pad(explode('/', $rule, 2), 2, '');
        $network = trim((string)$network);
        $prefixRaw = trim((string)$prefixRaw);

        if ($network === '' || $prefixRaw === '' || !ctype_digit($prefixRaw)) {
            return false;
        }

        if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
        ) {
            $prefix = (int)$prefixRaw;
            if ($prefix < 0 || $prefix > 32) {
                return false;
            }
            return $this->ipv4CidrContains($clientIp, $network, $prefix);
        }

        if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
        ) {
            $prefix = (int)$prefixRaw;
            if ($prefix < 0 || $prefix > 128) {
                return false;
            }
            return $this->ipv6CidrContains($clientIp, $network, $prefix);
        }

        return false;
    }

    private function ipv4CidrContains(string $clientIp, string $networkIp, int $prefix): bool
    {
        $clientLong = ip2long($clientIp);
        $networkLong = ip2long($networkIp);
        if ($clientLong === false || $networkLong === false) {
            return false;
        }

        if ($prefix === 0) {
            return true;
        }

        $mask = -1 << (32 - $prefix);
        return (($clientLong & $mask) === ($networkLong & $mask));
    }

    private function ipv6CidrContains(string $clientIp, string $networkIp, int $prefix): bool
    {
        $clientPacked = @inet_pton($clientIp);
        $networkPacked = @inet_pton($networkIp);
        if ($clientPacked === false || $networkPacked === false) {
            return false;
        }

        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;

        if ($bytes > 0 && substr($clientPacked, 0, $bytes) !== substr($networkPacked, 0, $bytes)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $clientByte = ord($clientPacked[$bytes]);
        $networkByte = ord($networkPacked[$bytes]);
        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return (($clientByte & $mask) === ($networkByte & $mask));
    }

    protected function isHttpsRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }

    protected function checkRateLimit(string $action, int $maxRequests, int $timeWindowSeconds): void
    {
        $ip = $this->getClientIp();
        $redis = Database::redis();

        if ($redis !== null) {
            try {
                $redisKey = 'rl:v1:' . hash('sha256', $action . '|' . $ip);
                $now = microtime(true);
                $windowStart = $now - $timeWindowSeconds;

                $redis->zRemRangeByScore($redisKey, (string)0, (string)$windowStart);
                $count = (int)$redis->zCard($redisKey);
                if ($count >= $maxRequests) {
                    $this->json(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
                    exit;
                }

                $member = bin2hex(random_bytes(8)) . ':' . (string)$now;
                $redis->zAdd($redisKey, $now, $member);
                $redis->expire($redisKey, $timeWindowSeconds + 5);
                return;
            } catch (\Throwable $e) {
                // 降级文件存储
            }
        }

        $cacheDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $now = time();
        $cutoff = $now - $timeWindowSeconds;

        $key = $action . '|' . $ip;
        $filePath = $cacheDir . DIRECTORY_SEPARATOR . 'rate_limit_' . md5($key) . '.json';

        $fh = @fopen($filePath, 'c+');
        if ($fh === false) {
            return;
        }

        try {
            flock($fh, LOCK_EX);
            $raw = stream_get_contents($fh);

            $timestamps = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['timestamps']) && is_array($decoded['timestamps'])) {
                    $timestamps = $decoded['timestamps'];
                }
            }

            $timestamps = array_map(static function ($v): int {
                return (int)$v;
            }, $timestamps);

            $timestamps = array_values(array_filter($timestamps, static function (int $ts) use ($cutoff): bool {
                return $ts >= $cutoff;
            }));

            if (count($timestamps) >= $maxRequests) {
                $this->json(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
                exit;
            }

            $timestamps[] = $now;

            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode(['timestamps' => $timestamps], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * OneBot V11（NapCat）群消息推送：极短超时 + 静默失败
     *
     * 目标：避免机器人宕机/网络阻塞导致后台发布公告请求卡死或报错。
     */
    protected static function sendQQGroupMessage(string $message): void
    {
        // Basic guards: avoid sending empty messages or misconfigured bot.
        if (trim($message) === '') {
            return;
        }
        if (!defined('QQ_BOT_API_URL') || !defined('QQ_GROUP_ID')) {
            return;
        }

        $apiBase = (string)QQ_BOT_API_URL;
        if ($apiBase === '') {
            return;
        }

        $groupId = (int)QQ_GROUP_ID;
        if ($groupId <= 0) {
            return;
        }

        $endpoint = rtrim($apiBase, '/') . '/send_group_msg';
        $payload = [
            'group_id' => $groupId,
            'message' => $message,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return;
        }

        // keep it short to protect admin request latency
        $connectTimeout = 2; // seconds
        $timeout = 4; // seconds

        try {
            if (function_exists('curl_init')) {
                $ch = @curl_init($endpoint);
                if ($ch === false) {
                    return;
                }

                @curl_setopt($ch, CURLOPT_POST, true);
                @curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
                @curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                @curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                if (defined('CURLOPT_NOSIGNAL')) {
                    @curl_setopt($ch, CURLOPT_NOSIGNAL, true);
                }

                @curl_exec($ch);
                @curl_close($ch);
                return;
            }

            // Fallback when curl extension is unavailable.
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $json,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);

            @file_get_contents($endpoint, false, $context);
        } catch (\Throwable $e) {
            // Silent ignore.
        }
    }
}

