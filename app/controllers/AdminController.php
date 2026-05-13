<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\ApiCode;
use Core\ApiResponse;
use Core\BaiduSEO;
use Core\Database;
use Core\EmailDomainWhitelist;
use Core\ImageProcessor;
use Core\MinecraftUuid;
use Model\Feedback;
use Model\SigninRewardConfig;
use Model\User;

class AdminController extends Controller
{
    private User $users;
    private SigninRewardConfig $signinRewards;
    private Feedback $feedbacks;

    public function __construct()
    {
        parent::__construct();
        $this->users = new User();
        $this->signinRewards = new SigninRewardConfig();
        $this->feedbacks = new Feedback();
        if (!$this->isRealtimeTicketVerifyEndpoint()) {
            $this->requireAdmin();
        }
    }

    private function isRealtimeTicketVerifyEndpoint(): bool
    {
        $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        return $path === '/admin/realtime-ticket/verify';
    }

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            if ($this->isAjaxRequest() || array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo ApiResponse::error(ApiCode::AUTH_INVALID, 'Unauthorized');
                exit;
            }

            header('Location: /auth/login');
            exit;
        }
    }

    private function completeAdminAction(string $redirectUrl, array $data = [], string $message = 'ok'): void
    {
        $isAjax = $this->isAjaxRequest() || array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST);
        if ($isAjax && str_contains($redirectUrl, 'err=')) {
            $query = (string)(parse_url($redirectUrl, PHP_URL_QUERY) ?? '');
            $params = [];
            if ($query !== '') {
                parse_str($query, $params);
            }
            $err = trim((string)($params['err'] ?? ''));
            if ($err !== '') {
                $data['error'] = $err;
                $resolvedMessage = $message === 'ok' ? ('error: ' . $err) : $message;
                $this->redirectOrJson($redirectUrl, $data, $resolvedMessage, 400);
                return;
            }
        }

        $this->redirectOrJson($redirectUrl, $data, $message);
    }

    /**
     * @return array{
     *   enable_realtime_panel: bool,
     *   ws_url: string,
     *   ws_auth_token: string,
     *   ws_ticket_endpoint: string,
     *   ws_ticket_query_param: string,
     *   ws_ticket_ttl_seconds: int,
     *   reconnect_interval_ms: int
     * }
     */
    private function getRealtimePanelConfig(): array
    {
        $enabled = defined('REALTIME_ENABLE_PANEL') ? (bool)REALTIME_ENABLE_PANEL : false;
        $wsUrl = defined('REALTIME_WS_URL') ? trim((string)REALTIME_WS_URL) : '';
        $ticketTtl = defined('REALTIME_WS_TICKET_TTL_SECONDS') ? (int)REALTIME_WS_TICKET_TTL_SECONDS : 120;
        $ticketQueryParam = defined('REALTIME_WS_TICKET_QUERY_PARAM') ? trim((string)REALTIME_WS_TICKET_QUERY_PARAM) : 'token';
        if ($ticketQueryParam === '') {
            $ticketQueryParam = 'token';
        }
        $reconnectIntervalMs = defined('REALTIME_RECONNECT_INTERVAL_MS') ? (int)REALTIME_RECONNECT_INTERVAL_MS : 3000;

        return [
            'enable_realtime_panel' => $enabled,
            'ws_url' => $wsUrl,
            // Keep this legacy field for frontend compatibility, but never expose long-lived token.
            'ws_auth_token' => '',
            'ws_ticket_endpoint' => '/admin/realtime-ticket',
            'ws_ticket_query_param' => $ticketQueryParam,
            'ws_ticket_ttl_seconds' => max(60, min(300, $ticketTtl)),
            'reconnect_interval_ms' => max(500, $reconnectIntervalMs),
        ];
    }

    /**
     * Issue a short-lived signed ticket for admin realtime panel WS connect.
     *
     * @return array{ticket:string,expires_at:int}
     */
    private function issueRealtimeWsTicket(): array
    {
        $ttl = defined('REALTIME_WS_TICKET_TTL_SECONDS') ? (int)REALTIME_WS_TICKET_TTL_SECONDS : 120;
        $ttl = max(60, min(300, $ttl));
        $now = time();
        $expiresAt = $now + $ttl;

        $secret = defined('REALTIME_TICKET_VERIFY_TOKEN') ? trim((string)REALTIME_TICKET_VERIFY_TOKEN) : '';
        if ($secret === '') {
            throw new \RuntimeException('REALTIME_TICKET_VERIFY_TOKEN is not configured');
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || ($_SESSION['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Admin session is required');
        }

        $payload = [
            'v' => 1,
            'typ' => 'admin_ws',
            'uid' => $userId,
            'role' => 'admin',
            'iat' => $now,
            'exp' => $expiresAt,
            'nonce' => bin2hex(random_bytes(12)),
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson)) {
            throw new \RuntimeException('Failed to encode realtime ticket payload');
        }

        $payloadB64 = $this->base64UrlEncode($payloadJson);
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $payloadB64, $secret, true));
        $ticket = 'v1.' . $payloadB64 . '.' . $signature;

        if (strlen($ticket) > 256) {
            throw new \RuntimeException('Realtime ticket is too long');
        }

        return [
            'ticket' => $ticket,
            'expires_at' => $expiresAt,
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : null;
    }

    public function realtimeTicket(): void
    {
        $config = $this->getRealtimePanelConfig();
        if (($config['enable_realtime_panel'] ?? false) !== true) {
            $this->json(['success' => false, 'message' => 'Realtime panel disabled'], 403);
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        try {
            $issued = $this->issueRealtimeWsTicket();
            $ttl = max(1, (int)$issued['expires_at'] - time());
            $this->json([
                'success' => true,
                'message' => 'ok',
                'ticket' => (string)$issued['ticket'],
                'expires_at' => (int)$issued['expires_at'],
                'expires_in' => $ttl,
                'ticket_query_param' => (string)($config['ws_ticket_query_param'] ?? 'token'),
                // WS service must verify this ticket.
                'requires_ws_server_ticket_support' => true,
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Failed to issue realtime ticket'], 500);
        }
    }

    public function realtimeTicketVerify(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->failRealtimeTicketVerify('invalid ticket', 405);
            return;
        }

        $requestContext = [
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
            'origin' => '',
            'path' => '/ws/admin',
            'ip' => $this->getClientIp(),
            'ticket_hash_prefix' => '',
        ];

        $providedToken = $this->extractRealtimeVerifyToken();
        $expectedToken = defined('REALTIME_TICKET_VERIFY_TOKEN') ? trim((string)REALTIME_TICKET_VERIFY_TOKEN) : '';
        if ($expectedToken === '') {
            $allowEmptyTokenInDev = APP_ENV === 'development'
                && (defined('REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN') ? (bool)REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN : false);
            if (!$allowEmptyTokenInDev) {
                $this->logRealtimeTicketVerifyFailure('verify_token_not_configured', $requestContext);
                $this->failRealtimeTicketVerify('invalid ticket', 503);
                return;
            }
            header('X-Realtime-Verify-Auth-Mode: dev-empty-token');
        } elseif (!$this->isRealtimeVerifyTokenAllowed($providedToken)) {
            $this->logRealtimeTicketVerifyFailure('verify_token_invalid', $requestContext);
            $this->failRealtimeTicketVerify('invalid ticket', 401);
            return;
        }

        try {
            $body = $this->readRealtimeVerifyJsonBody();
        } catch (\RuntimeException $e) {
            $this->logRealtimeTicketVerifyFailure('invalid_json_body', $requestContext);
            $status = (int)$e->getCode();
            $this->failRealtimeTicketVerify('invalid ticket', $status >= 400 ? $status : 400);
            return;
        }

        $ticket = (string)($body['ticket'] ?? '');
        $requestContext['origin'] = trim((string)($body['origin'] ?? ''));
        $requestPath = trim((string)($body['path'] ?? ''));
        if ($requestPath !== '') {
            $requestContext['path'] = $requestPath;
        }
        $bodyIp = trim((string)($body['ip'] ?? ''));
        if ($bodyIp !== '') {
            $requestContext['ip'] = $bodyIp;
        }
        if ($ticket !== '') {
            $requestContext['ticket_hash_prefix'] = substr(hash('sha256', $ticket), 0, 8);
        }

        if (!$this->isSafeRealtimeTicketFormat($ticket)) {
            $this->logRealtimeTicketVerifyFailure('invalid_ticket_format', $requestContext);
            $this->failRealtimeTicketVerify('invalid ticket', 401);
            return;
        }

        $verifyResult = $this->verifySignedRealtimeTicket($ticket);
        if (($verifyResult['ok'] ?? false) !== true) {
            $this->logRealtimeTicketVerifyFailure((string)($verifyResult['reason'] ?? 'signed_ticket_invalid'), $requestContext);
            $this->failRealtimeTicketVerify('invalid ticket', 401);
            return;
        }

        $payload = is_array($verifyResult['payload'] ?? null) ? $verifyResult['payload'] : [];
        $this->successRealtimeTicketVerify([
            'admin_id' => (int)($payload['uid'] ?? 0),
            'username' => (string)($payload['username'] ?? ''),
            'expires_at' => (int)($payload['exp'] ?? 0),
        ]);
    }

    private function extractRealtimeVerifyToken(): string
    {
        $authCandidates = [
            (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
            (string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''),
        ];
        foreach ($authCandidates as $header) {
            $value = trim($header);
            if ($value === '') {
                continue;
            }
            if (preg_match('/^Bearer\s+(.+)$/i', $value, $matches) !== 1) {
                continue;
            }
            $token = trim((string)($matches[1] ?? ''));
            if ($token !== '') {
                return $token;
            }
        }

        return trim((string)($_SERVER['HTTP_X_STELLAR_REALTIME_TOKEN'] ?? ''));
    }

    private function isRealtimeVerifyTokenAllowed(string $provided): bool
    {
        $expected = defined('REALTIME_TICKET_VERIFY_TOKEN') ? trim((string)REALTIME_TICKET_VERIFY_TOKEN) : '';
        if ($expected === '') {
            return APP_ENV === 'development'
                && (defined('REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN') ? (bool)REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN : false);
        }

        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * @return array{ticket:string,ip:string,userAgent:string,origin:string,path:string}
     */
    private function readRealtimeVerifyJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw)) {
            throw new \RuntimeException('invalid request body', 400);
        }
        if (strlen($raw) > 16384) {
            throw new \RuntimeException('request body too large', 400);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('invalid json', 400);
        }

        return [
            'ticket' => isset($decoded['ticket']) ? (string)$decoded['ticket'] : '',
            'ip' => isset($decoded['ip']) ? trim((string)$decoded['ip']) : '',
            'userAgent' => isset($decoded['userAgent']) ? trim((string)$decoded['userAgent']) : '',
            'origin' => isset($decoded['origin']) ? trim((string)$decoded['origin']) : '',
            'path' => isset($decoded['path']) ? trim((string)$decoded['path']) : '',
        ];
    }

    private function isSafeRealtimeTicketFormat(string $ticket): bool
    {
        if ($ticket === '') {
            return false;
        }
        if ($ticket !== trim($ticket)) {
            return false;
        }
        $length = strlen($ticket);
        if ($length < 32 || $length > 256) {
            return false;
        }
        if (preg_match('/[\s\x00-\x1F\x7F]/', $ticket) === 1) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._~-]+$/', $ticket) === 1;
    }

    /**
     * @return array{ok:bool,reason?:string,payload?:array{uid:int,username:string,exp:int}}
     */
    private function verifySignedRealtimeTicket(string $ticket): array
    {
        $secret = defined('REALTIME_TICKET_VERIFY_TOKEN') ? trim((string)REALTIME_TICKET_VERIFY_TOKEN) : '';
        if ($secret === '') {
            return ['ok' => false, 'reason' => 'verify_token_not_configured'];
        }

        $parts = explode('.', $ticket);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            return ['ok' => false, 'reason' => 'invalid_signed_ticket_format'];
        }

        [, $payloadB64, $signatureB64] = $parts;
        if ($payloadB64 === '' || $signatureB64 === '') {
            return ['ok' => false, 'reason' => 'invalid_signed_ticket_parts'];
        }

        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $payloadB64, $secret, true));
        if (!hash_equals($expectedSignature, $signatureB64)) {
            return ['ok' => false, 'reason' => 'signed_ticket_signature_mismatch'];
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        if (!is_string($payloadJson) || $payloadJson === '') {
            return ['ok' => false, 'reason' => 'signed_ticket_payload_decode_failed'];
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'reason' => 'signed_ticket_payload_json_invalid'];
        }

        if ((int)($payload['v'] ?? 0) !== 1) {
            return ['ok' => false, 'reason' => 'signed_ticket_version_invalid'];
        }

        if ((string)($payload['typ'] ?? '') !== 'admin_ws') {
            return ['ok' => false, 'reason' => 'signed_ticket_type_invalid'];
        }

        if ((string)($payload['role'] ?? '') !== 'admin') {
            return ['ok' => false, 'reason' => 'signed_ticket_role_invalid'];
        }

        $uid = (int)($payload['uid'] ?? 0);
        if ($uid <= 0) {
            return ['ok' => false, 'reason' => 'signed_ticket_uid_invalid'];
        }

        $now = time();
        $iat = (int)($payload['iat'] ?? 0);
        $exp = (int)($payload['exp'] ?? 0);

        if ($iat <= 0 || $iat > $now + 30) {
            return ['ok' => false, 'reason' => 'signed_ticket_iat_invalid'];
        }

        if ($exp <= $now) {
            return ['ok' => false, 'reason' => 'signed_ticket_expired'];
        }

        if ($exp <= $iat) {
            return ['ok' => false, 'reason' => 'signed_ticket_time_window_invalid'];
        }

        if ($exp - $iat > 300) {
            return ['ok' => false, 'reason' => 'signed_ticket_ttl_too_long'];
        }

        $user = $this->users->findById($uid);
        if (!is_array($user)) {
            return ['ok' => false, 'reason' => 'user_not_found'];
        }

        if ((string)($user['role'] ?? '') !== 'admin' || (string)($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'reason' => 'user_not_active_admin'];
        }

        return [
            'ok' => true,
            'payload' => [
                'uid' => $uid,
                'username' => (string)($user['username'] ?? ''),
                'exp' => $exp,
            ],
        ];
    }

    private function failRealtimeTicketVerify(string $message, int $status = 401): void
    {
        $this->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    private function successRealtimeTicketVerify(array $data): void
    {
        $this->json([
            'success' => true,
            'ok' => true,
            'data' => $data,
        ]);
    }

    private function logRealtimeTicketVerifyFailure(string $reason, array $context = []): void
    {
        $payload = [
            'reason' => $reason,
            'user_id' => (int)($context['user_id'] ?? ($_SESSION['user_id'] ?? 0)),
            'origin' => trim((string)($context['origin'] ?? '')),
            'path' => trim((string)($context['path'] ?? '')),
            'ip' => trim((string)($context['ip'] ?? $this->getClientIp())),
            'ticket_hash_prefix' => trim((string)($context['ticket_hash_prefix'] ?? '')),
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            error_log('[RealtimeTicketVerify] ' . $encoded);
            return;
        }

        error_log('[RealtimeTicketVerify] reason=' . $reason);
    }

    public function realtime(): void
    {
        $config = $this->getRealtimePanelConfig();
        if (($config['enable_realtime_panel'] ?? false) !== true) {
            $this->completeAdminAction('/admin?tab=dashboard');
        }

        $this->completeAdminAction('/admin?tab=realtime');
    }

    public function dashboard(): string
    {
        $this->generateCsrfToken();
        $realtimeWsConfig = $this->getRealtimePanelConfig();
        $db = Database::connection();
        $userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $announcementCount = (int)$db->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
        $galleryCount = (int)$db->query('SELECT COUNT(*) FROM gallery_images')->fetchColumn();

        $perPage = 20;
        $readPage = static function (string $param): int {
            $page = (int)($_GET[$param] ?? 1);
            return $page > 0 ? $page : 1;
        };
        $buildPagination = static function (int $total, int $page, int $perPage, string $param): array {
            $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
            $currentPage = min(max(1, $page), $totalPages);
            return [
                'page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'param' => $param,
            ];
        };

        $activeTab = trim((string)($_GET['tab'] ?? 'dashboard'));
        if ($activeTab === 'checkin-rewards') {
            $activeTab = 'signin-rewards';
        }
        $allowedTabs = ['dashboard', 'realtime', 'signin-rewards', 'redeem', 'players', 'feedback', 'announcements', 'milestones', 'gallery', 'site-settings', 'team', 'ip-whitelist', 'ip-blacklist'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'dashboard';
        }

        $players = [];
        $feedbackList = [];
        $announcements = [];
        $milestones = [];
        $images = [];
        $ipBlacklist = [];
        $siteSettings = [];
        $teamMembers = [];
        $ipWhitelist = [];
        $signinRewardEditorState = [];

        $playersPagination = $buildPagination(0, 1, $perPage, 'players_page');
        $announcementsPagination = $buildPagination(0, 1, $perPage, 'announcements_page');
        $milestonesPagination = $buildPagination(0, 1, $perPage, 'milestones_page');
        $imagesPagination = $buildPagination(0, 1, $perPage, 'gallery_page');
        $ipBlacklistPagination = $buildPagination(0, 1, $perPage, 'blacklist_page');
        $teamMembersPagination = $buildPagination(0, 1, $perPage, 'team_page');
        $ipWhitelistPagination = $buildPagination(0, 1, $perPage, 'whitelist_page');
        $feedbackPagination = $buildPagination(0, 1, $perPage, 'feedback_page');
        $feedbackFilters = [
            'q' => '',
            'status' => '',
            'category' => '',
        ];
        $feedbackLoadError = '';

        if ($activeTab === 'players') {
            $playersPagination = $buildPagination($userCount, $readPage('players_page'), $perPage, 'players_page');
            $playersOffset = ($playersPagination['page'] - 1) * $perPage;
            $players = $db->query(sprintf(
                'SELECT id, username, mc_username, mc_uuid, email, role, status, created_at, ip, regip, lastlogin, regdate FROM users ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $perPage,
                $playersOffset
            ))->fetchAll() ?: [];
        }

        if ($activeTab === 'announcements') {
            $announcementsPagination = $buildPagination($announcementCount, $readPage('announcements_page'), $perPage, 'announcements_page');
            $announcementsOffset = ($announcementsPagination['page'] - 1) * $perPage;
            $announcements = $db->query(sprintf(
                'SELECT * FROM announcements ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $perPage,
                $announcementsOffset
            ))->fetchAll() ?: [];
        }

        if ($activeTab === 'milestones') {
            $milestonesCount = (int)$db->query('SELECT COUNT(*) FROM milestones')->fetchColumn();
            $milestonesPagination = $buildPagination($milestonesCount, $readPage('milestones_page'), $perPage, 'milestones_page');
            $milestonesOffset = ($milestonesPagination['page'] - 1) * $perPage;
            $milestones = $db->query(sprintf(
                'SELECT * FROM milestones ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
                $perPage,
                $milestonesOffset
            ))->fetchAll() ?: [];
        }

        if ($activeTab === 'gallery') {
            $imagesPagination = $buildPagination($galleryCount, $readPage('gallery_page'), $perPage, 'gallery_page');
            $imagesOffset = ($imagesPagination['page'] - 1) * $perPage;
            $images = $db->query(sprintf(
                'SELECT * FROM gallery_images ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $perPage,
                $imagesOffset
            ))->fetchAll() ?: [];
        }

        if ($activeTab === 'ip-blacklist') {
            try {
                $ipBlacklistCount = (int)$db->query('SELECT COUNT(*) FROM ip_blacklist')->fetchColumn();
                $ipBlacklistPagination = $buildPagination($ipBlacklistCount, $readPage('blacklist_page'), $perPage, 'blacklist_page');
                $ipBlacklistOffset = ($ipBlacklistPagination['page'] - 1) * $perPage;
                $ipBlacklist = $db->query(sprintf(
                    'SELECT id, ip_cidr, reason, created_at FROM ip_blacklist ORDER BY id DESC LIMIT %d OFFSET %d',
                    $perPage,
                    $ipBlacklistOffset
                ))->fetchAll() ?: [];
            } catch (\Throwable $e) {
                $ipBlacklist = [];
            }
        }

        if ($activeTab === 'site-settings') {
            try {
                $siteSettings = $db->query('SELECT id, setting_key, setting_value, description FROM site_settings ORDER BY id ASC')->fetchAll() ?: [];
            } catch (\Throwable $e) {
                $siteSettings = [];
            }
        }

        if ($activeTab === 'team') {
            try {
                $teamMembersCount = (int)$db->query('SELECT COUNT(*) FROM team_members')->fetchColumn();
                $teamMembersPagination = $buildPagination($teamMembersCount, $readPage('team_page'), $perPage, 'team_page');
                $teamMembersOffset = ($teamMembersPagination['page'] - 1) * $perPage;
                $teamMembers = $db->query(sprintf(
                    'SELECT id, username, role, created_at FROM team_members ORDER BY id ASC LIMIT %d OFFSET %d',
                    $perPage,
                    $teamMembersOffset
                ))->fetchAll() ?: [];
            } catch (\Throwable $e) {
                $teamMembers = [];
            }
        }

        if ($activeTab === 'ip-whitelist') {
            try {
                $ipWhitelistCount = (int)$db->query('SELECT COUNT(*) FROM ip_whitelist')->fetchColumn();
                $ipWhitelistPagination = $buildPagination($ipWhitelistCount, $readPage('whitelist_page'), $perPage, 'whitelist_page');
                $ipWhitelistOffset = ($ipWhitelistPagination['page'] - 1) * $perPage;
                $ipWhitelist = $db->query(sprintf(
                    'SELECT id, ip_cidr, reason, created_at FROM ip_whitelist ORDER BY id DESC LIMIT %d OFFSET %d',
                    $perPage,
                    $ipWhitelistOffset
                ))->fetchAll() ?: [];
            } catch (\Throwable $e) {
                $ipWhitelist = [];
            }
        }

        if ($activeTab === 'feedback') {
            $feedbackFilters = [
                'q' => trim((string)($_GET['feedback_q'] ?? '')),
                'status' => strtolower(trim((string)($_GET['feedback_status'] ?? ''))),
                'category' => strtolower(trim((string)($_GET['feedback_category'] ?? ''))),
            ];

            if (!in_array($feedbackFilters['status'], Feedback::allowedStatuses(), true)) {
                $feedbackFilters['status'] = '';
            }
            if (!in_array($feedbackFilters['category'], Feedback::allowedCategories(), true)) {
                $feedbackFilters['category'] = '';
            }

            $feedbackPage = $readPage('feedback_page');
            try {
                $feedbackTotal = $this->feedbacks->countAdminFeedbackList($feedbackFilters);
                $feedbackPagination = $buildPagination($feedbackTotal, $feedbackPage, $perPage, 'feedback_page');
                $feedbackList = $this->feedbacks->getAdminFeedbackList(
                    $feedbackFilters,
                    (int)$feedbackPagination['page'],
                    $perPage
                );
            } catch (\Throwable $e) {
                $feedbackList = [];
                $feedbackPagination = $buildPagination(0, 1, $perPage, 'feedback_page');
                $feedbackLoadError = '举报反馈功能暂不可用，请先执行数据库升级。';
                if (APP_ENV === 'development') {
                    $feedbackLoadError .= ' (' . $e->getMessage() . ')';
                }
            }
        }

        if ($activeTab === 'signin-rewards') {
            $signinRewardEditorState = $this->signinRewards->getAdminEditorState((int)($_SESSION['user_id'] ?? 0));
        }

        return $this->render('admin/dashboard', [
            'title' => '后台总览',
            'userCount' => $userCount,
            'announcementCount' => $announcementCount,
            'galleryCount' => $galleryCount,
            'players' => $players,
            'playersPagination' => $playersPagination,
            'feedbackList' => $feedbackList,
            'feedbackPagination' => $feedbackPagination,
            'feedbackFilters' => $feedbackFilters,
            'feedbackLoadError' => $feedbackLoadError,
            'announcements' => $announcements,
            'announcementsPagination' => $announcementsPagination,
            'milestones' => $milestones,
            'milestonesPagination' => $milestonesPagination,
            'images' => $images,
            'imagesPagination' => $imagesPagination,
            'ipBlacklist' => $ipBlacklist,
            'ipBlacklistPagination' => $ipBlacklistPagination,
            'siteSettings' => $siteSettings,
            'teamMembers' => $teamMembers,
            'teamMembersPagination' => $teamMembersPagination,
            'ipWhitelist' => $ipWhitelist,
            'ipWhitelistPagination' => $ipWhitelistPagination,
            'signinRewardEditorState' => $signinRewardEditorState,
            'realtimePanelEnabled' => (bool)$realtimeWsConfig['enable_realtime_panel'],
            'realtimeWsConfig' => $realtimeWsConfig,
        ]);
    }

    /**
     * 后台手动触发：百度“普通收录 -> API 提交”
     */
    public function manualSeoPush(): void
    {
        // 1) 管理员鉴权（构造器已校验，这里再做一次确保 Ajax 返回 JSON）
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->json(['status' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        // 2) CSRF 校验（Ajax 必须携带 csrf_token）
        $this->validateCsrfToken();

        try {
            $seo = new BaiduSEO();
            $urls = $seo->collectPublicUrls();
            $pushRes = $seo->pushUrlsToBaidu($urls);

            if (($pushRes['status'] ?? false) === true) {
                $this->json([
                    'status' => true,
                    'urls_count' => count($urls),
                    'success_count' => $pushRes['success_count'],
                    'remain' => $pushRes['remain'],
                    'not_same_site' => $pushRes['not_same_site'],
                    'not_valid' => $pushRes['not_valid'],
                    'message' => $pushRes['message'],
                ]);
                return;
            }

            $this->json([
                'status' => false,
                'urls_count' => count($urls),
                'success_count' => $pushRes['success_count'] ?? 0,
                'remain' => $pushRes['remain'] ?? null,
                'not_same_site' => $pushRes['not_same_site'] ?? null,
                'not_valid' => $pushRes['not_valid'] ?? null,
                'message' => $pushRes['message'] ?? '百度推送失败',
            ], 500);
        } catch (\Throwable $e) {
            $this->json([
                'status' => false,
                'message' => '系统异常：' . ($e->getMessage() !== '' ? $e->getMessage() : 'unknown'),
            ], 500);
        }
    }

    public function playerUpdate(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=players');
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'active');
        $role = (string)($_POST['role'] ?? 'player');
        $mcUsername = trim((string)($_POST['mc_username'] ?? ''));
        $mcUuid = trim((string)($_POST['mc_uuid'] ?? ''));

        $db = Database::connection();
        $stmt = $db->prepare('UPDATE users SET status = :status, role = :role, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':role' => $role,
            ':id' => $id,
        ]);

        if ($mcUsername !== '' && $mcUuid === '') {
            $mcUuid = MinecraftUuid::resolveUuid($mcUsername);
        }

        if ($mcUsername === '' && $mcUuid === '') {
            $this->users->unbindCharacter($id);
        } else {
            $this->users->bindCharacter($id, $mcUsername, $mcUuid);
        }

        $this->completeAdminAction('/admin?tab=players');
    }

    public function playerDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=players');
        $targetUserId = (int)($_POST['id'] ?? 0);
        $adminUserId = (int)($_SESSION['user_id'] ?? 0);

        if ($targetUserId <= 0 || $adminUserId <= 0) {
            $this->completeAdminAction('/admin?tab=players&err=invalid_user', [], 'Invalid user id');
            return;
        }

        if ($targetUserId === $adminUserId) {
            $this->completeAdminAction('/admin?tab=players&err=self_delete', [], 'Cannot delete your own admin account');
            return;
        }

        $targetUser = $this->users->findById($targetUserId);
        if ($targetUser === null) {
            $this->completeAdminAction('/admin?tab=players&err=user_not_found', [], 'User not found');
            return;
        }

        $targetRole = strtolower(trim((string)($targetUser['role'] ?? 'player')));
        $targetStatus = strtolower(trim((string)($targetUser['status'] ?? 'active')));
        if ($targetRole === 'admin' && $targetStatus === 'active') {
            $otherActiveAdmins = $this->users->countOtherActiveAdmins($targetUserId);
            if ($otherActiveAdmins <= 0) {
                $this->completeAdminAction('/admin?tab=players&err=last_admin', [], 'Cannot delete the last active admin');
                return;
            }
        }

        $deleted = $this->users->softDeleteByAdmin($targetUserId, $adminUserId, 'admin_panel_player_delete');
        if (!$deleted) {
            $this->completeAdminAction('/admin?tab=players&err=delete_failed', [], 'User deletion failed');
            return;
        }

        $this->completeAdminAction('/admin?tab=players');
    }

    public function playerUnbind(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=players');
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->users->unbindCharacter($id);
        }
        $this->completeAdminAction('/admin?tab=players');
    }

    public function feedbackAttachment(): void
    {
        $attachmentId = (int)($_GET['id'] ?? 0);
        if ($attachmentId <= 0) {
            http_response_code(404);
            exit;
        }

        $attachment = $this->feedbacks->getAttachmentById($attachmentId);
        if (!is_array($attachment)) {
            http_response_code(404);
            exit;
        }

        $absolutePath = $this->feedbacks->resolveAttachmentAbsolutePath((string)($attachment['file_path'] ?? ''));
        if ($absolutePath === null || !is_file($absolutePath)) {
            http_response_code(404);
            exit;
        }

        $mimeType = trim((string)($attachment['mime_type'] ?? ''));
        if ($mimeType === '' || preg_match('/^[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*$/i', $mimeType) !== 1) {
            $mimeType = 'application/octet-stream';
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string)filesize($absolutePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($absolutePath);
        exit;
    }

    public function feedbackUpdate(): void
    {
        $isAjax = $this->isAjaxRequest() || array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST);
        if ($isAjax) {
            $this->generateCsrfToken();
            $token = (string)($_POST['csrf_token'] ?? '');
            $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
            if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
                $this->json(['success' => false, 'message' => 'CSRF token mismatch'], 403);
                return;
            }
        } else {
            $this->validateCsrfForFormPost('/admin?tab=feedback');
        }

        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        if ($feedbackId <= 0) {
            $this->completeAdminAction('/admin?tab=feedback&err=feedback_id', [], 'Invalid feedback id');
            return;
        }

        $status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
        if (!in_array($status, Feedback::allowedStatuses(), true)) {
            $this->completeAdminAction('/admin?tab=feedback&err=status', [], 'Invalid feedback status');
            return;
        }

        $adminReply = trim((string)($_POST['admin_reply'] ?? ''));
        if (mb_strlen($adminReply, 'UTF-8') > 5000) {
            $this->completeAdminAction('/admin?tab=feedback&err=reply_len', [], 'Admin reply is too long');
            return;
        }
        if ($status === 'need_more_info' && $adminReply === '') {
            $this->completeAdminAction(
                '/admin?tab=feedback&err=reply_required',
                [],
                'Admin reply is required when status is need_more_info'
            );
            return;
        }

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) {
            $this->completeAdminAction('/admin?tab=feedback&err=auth', [], 'Unauthorized');
            return;
        }

        try {
            $updated = $this->feedbacks->updateFeedbackStatus($feedbackId, $status, $adminReply, $adminId);
            if (!$updated) {
                $this->completeAdminAction('/admin?tab=feedback&err=not_found', [], 'Feedback not found');
                return;
            }
        } catch (\Throwable $e) {
            $this->completeAdminAction('/admin?tab=feedback&err=save', [], 'Feedback update failed');
            return;
        }

        $this->completeAdminAction(
            '/admin?tab=feedback&saved=1',
            ['feedback_id' => $feedbackId, 'status' => $status],
            'Feedback updated'
        );
    }

    public function legacyCheckinRewardsRedirect(): void
    {
        $this->completeAdminAction('/admin?tab=signin-rewards');
    }

    public function checkinRewardSave(): void
    {
        // Legacy endpoint compatibility.
        $this->completeAdminAction('/admin?tab=signin-rewards');
    }

    public function signinRewardsSaveDraft(): void
    {
        $this->validateCsrfToken();
        $adminUserId = (int)($_SESSION['user_id'] ?? 0);

        $payloadRaw = trim((string)($_POST['payload_json'] ?? ''));
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $this->redirectOrJson(
                '/admin?tab=signin-rewards&err=invalid_payload',
                ['success' => false, 'ok' => false, 'message' => '请求数据无效：payload_json'],
                '请求数据无效：payload_json',
                400
            );
            return;
        }

        $result = $this->signinRewards->saveDraftFromAdmin($payload, $adminUserId);
        if (($result['ok'] ?? false) !== true) {
            $this->redirectOrJson(
                '/admin?tab=signin-rewards&err=save_draft',
                ['success' => false, 'ok' => false, 'message' => (string)($result['message'] ?? '保存草稿失败')],
                (string)($result['message'] ?? '保存草稿失败'),
                (int)($result['status'] ?? 500)
            );
            return;
        }

        $this->redirectOrJson(
            '/admin?tab=signin-rewards&saved=1',
            [
                'success' => true,
                'ok' => true,
                'message' => '草稿已保存，但不会影响普通玩家签到。请点击“发布到生效日期”后，配置才会在指定日期生效。',
            ],
            '草稿已保存，但不会影响普通玩家签到。请点击“发布到生效日期”后，配置才会在指定日期生效。',
            200
        );
    }

    public function signinRewardsPublish(): void
    {
        $this->validateCsrfToken();
        $adminUserId = (int)($_SESSION['user_id'] ?? 0);
        $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));
        $effectiveDate = $effectiveDate === '' ? null : $effectiveDate;

        $result = $this->signinRewards->publishDraft($effectiveDate, $adminUserId);
        if (($result['ok'] ?? false) !== true) {
            $errorCode = trim((string)($result['code'] ?? 'publish'));
            if ($errorCode === '') {
                $errorCode = 'publish';
            }
            $errorMessage = (string)($result['message'] ?? '发布失败');
            $redirect = '/admin?tab=signin-rewards&err=' . rawurlencode($errorCode);
            if ($errorMessage !== '') {
                $redirect .= '&err_msg=' . rawurlencode($errorMessage);
            }

            $this->redirectOrJson(
                $redirect,
                ['success' => false, 'ok' => false, 'message' => $errorMessage, 'code' => $errorCode],
                $errorMessage,
                (int)($result['status'] ?? 500)
            );
            return;
        }

        $effectiveOut = trim((string)($result['effective_date'] ?? ''));
        if ($effectiveOut === '') {
            $effectiveOut = date('Y-m-d', strtotime('+1 day'));
        }
        $redirect = '/admin?tab=signin-rewards&published=1';
        if ($effectiveOut !== '') {
            $redirect .= '&effective_date=' . rawurlencode($effectiveOut);
        }

        $this->redirectOrJson(
            $redirect,
            [
                'success' => true,
                'ok' => true,
                'message' => '配置已发布并排期，将于 ' . $effectiveOut . ' 00:00 后成为当前生效配置。',
                'effective_date' => $effectiveOut,
            ],
            '配置已发布并排期，将于 ' . $effectiveOut . ' 00:00 后成为当前生效配置。',
            200
        );
    }

    public function signinRewardsDeleteRule(): void
    {
        $this->validateCsrfToken();
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $result = $this->signinRewards->deleteDraftRule($ruleId);

        if (($result['ok'] ?? false) !== true) {
            $this->redirectOrJson(
                '/admin?tab=signin-rewards&err=delete_rule',
                ['success' => false, 'ok' => false, 'message' => (string)($result['message'] ?? '删除规则失败')],
                (string)($result['message'] ?? '删除规则失败'),
                (int)($result['status'] ?? 500)
            );
            return;
        }

        $this->redirectOrJson(
            '/admin?tab=signin-rewards&deleted=1',
            ['success' => true, 'ok' => true, 'message' => '规则已删除'],
            '规则已删除',
            200
        );
    }

    public function signinRewardsTestSend(): void
    {
        $this->validateCsrfToken();
        $adminUserId = (int)($_SESSION['user_id'] ?? 0);

        $input = [
            'target_player_name' => trim((string)($_POST['target_player_name'] ?? '')),
            'target_player_uuid' => trim((string)($_POST['target_player_uuid'] ?? '')),
            'continuous' => (int)($_POST['continuous'] ?? 1),
            'total' => (int)($_POST['total'] ?? 1),
            'month_days' => (int)($_POST['month_days'] ?? 1),
            'sign_date' => trim((string)($_POST['sign_date'] ?? '')),
            'server_id' => trim((string)($_POST['server_id'] ?? '')),
        ];

        $result = $this->signinRewards->createTestRewardOutbox($adminUserId, $input);
        if (($result['ok'] ?? false) !== true) {
            $this->redirectOrJson(
                '/admin?tab=signin-rewards&err=test_send',
                ['success' => false, 'ok' => false, 'message' => (string)($result['message'] ?? '测试发送失败')],
                (string)($result['message'] ?? '测试发送失败'),
                (int)($result['status'] ?? 500)
            );
            return;
        }

        $this->redirectOrJson(
            '/admin?tab=signin-rewards&test_sent=1',
            [
                'success' => true,
                'ok' => true,
                'message' => '测试奖励已入队，source=signin_test，不会影响正式签到统计。',
                'request_id' => (string)($result['request_id'] ?? ''),
                'payload' => is_array($result['payload'] ?? null) ? $result['payload'] : [],
                'target' => is_array($result['target'] ?? null) ? $result['target'] : [],
            ],
            '测试奖励已入队，source=signin_test，不会影响正式签到统计。',
            200
        );
    }

    public function announcementSave(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=announcements');
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;
        $publishMode = (string)($_POST['publish_mode'] ?? 'immediate'); // immediate | scheduled
        $publishTimeInput = trim((string)($_POST['publish_time'] ?? '')); // datetime-local: YYYY-MM-DDTHH:MM

        $db = Database::connection();
        $now = date('Y-m-d H:i:s');
        $publishAt = $now;

        if ($isPublished === 1) {
            if ($publishMode === 'scheduled') {
                $normalized = str_replace('T', ' ', $publishTimeInput);
                $dt = null;

                if ($normalized !== '') {
                    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $normalized) ?: \DateTime::createFromFormat('Y-m-d H:i', $normalized);
                }

                $publishAt = $dt instanceof \DateTime ? $dt->format('Y-m-d H:i:s') : $now;
            } else {
                $publishAt = $now;
            }
        } else {
            // 草稿仍写入一个可用时间，避免 created_at 为空
            $publishAt = $now;
        }

        if ($id > 0) {
            $stmt = $db->prepare('UPDATE announcements SET title = :title, content = :content, is_published = :pub, created_at = :created_at, updated_at = NOW() WHERE id = :id');
            $ok = $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':pub' => $isPublished,
                ':created_at' => $publishAt,
                ':id' => $id,
            ]);

            if ($ok && $isPublished === 1) {
                $qqMsg = "【服务器新公告】\n标题：" . $title . "\n请前往网站查看详情！";
                self::sendQQGroupMessage($qqMsg);
            }
        } else {
            $stmt = $db->prepare('INSERT INTO announcements (title, content, is_published, created_at, updated_at) VALUES (:title, :content, :pub, :created_at, NOW())');
            $ok = $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':pub' => $isPublished,
                ':created_at' => $publishAt,
            ]);
            $newInsertId = (int)$db->lastInsertId();
            if ($ok && $isPublished === 1 && $newInsertId > 0) {
                $qqMsg = "【服务器新公告】\n标题：" . $title . "\n请前往网站查看详情！";
                self::sendQQGroupMessage($qqMsg);
            }

            $shouldPing = ($ok === true) && ($isPublished === 1) && ($newInsertId > 0) && (strtotime($publishAt) !== false) && (strtotime($publishAt) <= time()) && (BAIDU_PUSH_TOKEN !== '');
            if ($shouldPing) {
                try {
                    $newUrl = SITE_BASE_URL . '/announcements/view?id=' . $newInsertId;
                    $baiduApiUrl = 'https://data.zz.baidu.com/urls?site=' . urlencode(SITE_BASE_URL) . '&token=' . urlencode(BAIDU_PUSH_TOKEN);
                    $ch = curl_init($baiduApiUrl);
                    if ($ch !== false) {
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $newUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
                        @curl_exec($ch);
                        curl_close($ch);
                    }
                } catch (\Throwable $e) {
                    // 静默忽略，不阻塞重定向
                }
            }
        }

        $this->completeAdminAction('/admin?tab=announcements');
    }

    public function announcementDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=announcements');
        $id = (int)($_POST['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->completeAdminAction('/admin?tab=announcements');
    }

    public function milestoneSave(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=milestones');
        $id = (int)($_POST['id'] ?? 0);
        $milestoneDate = trim((string)($_POST['milestone_date'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $db = Database::connection();

        if ($id > 0) {
            $stmt = $db->prepare('UPDATE milestones SET milestone_date = :milestone_date, description = :description WHERE id = :id');
            $stmt->execute([
                ':milestone_date' => $milestoneDate,
                ':description' => $description,
                ':id' => $id,
            ]);
        } else {
            $stmt = $db->prepare('INSERT INTO milestones (milestone_date, description, created_at) VALUES (:milestone_date, :description, NOW())');
            $stmt->execute([
                ':milestone_date' => $milestoneDate,
                ':description' => $description,
            ]);
        }

        $this->completeAdminAction('/admin?tab=milestones');
    }

    public function milestoneDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=milestones');
        $id = (int)($_POST['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM milestones WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->completeAdminAction('/admin?tab=milestones');
    }

    public function galleryUpload(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=gallery');
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->completeAdminAction('/admin?tab=gallery');
        }

        $tmp = (string)($_FILES['image']['tmp_name'] ?? '');
        $name = basename((string)($_FILES['image']['name'] ?? ''));
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $this->completeAdminAction('/admin?tab=gallery');
        }

        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowedExt, true)) {
            $this->completeAdminAction('/admin?tab=gallery');
        }

        $imageMeta = @getimagesize($tmp);
        $mime = is_array($imageMeta) ? strtolower((string)($imageMeta['mime'] ?? '')) : '';
        $mimeExtMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($mimeExtMap[$mime])) {
            $this->completeAdminAction('/admin?tab=gallery');
        }

        $ext = $mimeExtMap[$mime];
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destRel = '/uploads/gallery/' . $safeName;
        $destAbs = PUBLIC_PATH . $destRel;

        if (!is_dir(dirname($destAbs))) {
            @mkdir(dirname($destAbs), 0775, true);
        }

        if (!move_uploaded_file($tmp, $destAbs)) {
            $this->completeAdminAction('/admin?tab=gallery');
        }

        ImageProcessor::generateGalleryResponsiveSet($destAbs);

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO gallery_images (title, description, image_path, created_at) VALUES (:title, :description, :image_path, NOW())');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':image_path' => $destRel,
        ]);

        $this->completeAdminAction('/admin?tab=gallery');
    }

    public function galleryDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=gallery');
        $id = (int)($_POST['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT image_path FROM gallery_images WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['image_path'])) {
            $filePath = PUBLIC_PATH . $row['image_path'];
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            $dir = dirname($filePath);
            $stem = pathinfo($filePath, PATHINFO_FILENAME);
            foreach (glob($dir . DIRECTORY_SEPARATOR . $stem . '-*.webp') ?: [] as $variant) {
                if (is_file($variant)) {
                    @unlink($variant);
                }
            }
        }
        $delStmt = $db->prepare('DELETE FROM gallery_images WHERE id = :id');
        $delStmt->execute([':id' => $id]);
        $this->completeAdminAction('/admin?tab=gallery');
    }

    public function ipBlacklistAdd(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-blacklist');
        $ipCidr = trim((string)($_POST['ip_cidr'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!$this->isValidIpCidrEntry($ipCidr)) {
            $this->completeAdminAction('/admin?tab=ip-blacklist&err=invalid');
        }

        $db = Database::connection();
        $dup = $db->prepare('SELECT id FROM ip_blacklist WHERE ip_cidr = :r LIMIT 1');
        $dup->execute([':r' => $ipCidr]);
        if ($dup->fetch()) {
            $this->completeAdminAction('/admin?tab=ip-blacklist&err=dup');
        }

        $stmt = $db->prepare('INSERT INTO ip_blacklist (ip_cidr, reason, created_at) VALUES (:ip_cidr, :reason, NOW())');
        $stmt->execute([
            ':ip_cidr' => $ipCidr,
            ':reason' => $reason,
        ]);

        $this->completeAdminAction('/admin?tab=ip-blacklist');
    }

    public function ipBlacklistDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-blacklist');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->completeAdminAction('/admin?tab=ip-blacklist');
        }

        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM ip_blacklist WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $this->completeAdminAction('/admin?tab=ip-blacklist');
    }

    public function saveSettings(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=site-settings');
        $db = Database::connection();
        $allowed = [];
        try {
            $stmtKeys = $db->query('SELECT setting_key FROM site_settings');
            foreach ($stmtKeys->fetchAll() ?: [] as $row) {
                $k = (string)($row['setting_key'] ?? '');
                if ($k !== '') {
                    $allowed[$k] = true;
                }
            }
        } catch (\Throwable $e) {
            $this->completeAdminAction('/admin?tab=site-settings');
        }

        $settingsIn = $_POST['settings'] ?? [];
        if (!is_array($settingsIn)) {
            $this->completeAdminAction('/admin?tab=site-settings');
        }

        $upd = $db->prepare('UPDATE site_settings SET setting_value = :v WHERE setting_key = :k');
        foreach ($settingsIn as $key => $rawVal) {
            $key = (string)$key;
            if ($key === '' || !isset($allowed[$key])) {
                continue;
            }
            $val = trim((string)$rawVal);
            if ($key === 'register_ip_limit') {
                if (!ctype_digit($val)) {
                    continue;
                }
                $n = (int)$val;
                if ($n > 9999) {
                    $n = 9999;
                }
                $val = (string)$n;
            } elseif ($key === 'whitelist_ignores_rate_limit') {
                $val = $val === '1' ? '1' : '0';
            } elseif ($key === 'email_domain_whitelist_enabled') {
                $val = $val === '1' ? '1' : '0';
            } elseif ($key === 'email_domain_whitelist') {
                $val = implode(',', EmailDomainWhitelist::normalize($val));
            } elseif ($key === 'email_code_expire_seconds') {
                if (!ctype_digit($val)) {
                    continue;
                }
                $val = (string)max(60, min(3600, (int)$val));
            } elseif ($key === 'email_code_send_cooldown_seconds') {
                if (!ctype_digit($val)) {
                    continue;
                }
                $val = (string)max(30, min(3600, (int)$val));
            } elseif ($key === 'audit_log_storage') {
                $val = strtolower($val);
                if (!in_array($val, ['mysql', 'file', 'both'], true)) {
                    continue;
                }
            }
            $upd->execute([':v' => $val, ':k' => $key]);
        }

        $this->completeAdminAction('/admin?tab=site-settings');
    }

    public function teamMemberSave(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=team');
        $id = (int)($_POST['id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));

        if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{1,32}$/', $username)) {
            $this->completeAdminAction('/admin?tab=team&err=invalid_user');
        }
        if ($role === '') {
            $role = '服务器成员';
        }
        if (mb_strlen($role, 'UTF-8') > 128) {
            $role = mb_substr($role, 0, 128, 'UTF-8');
        }

        try {
            $db = Database::connection();
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE team_members SET username = :u, role = :r WHERE id = :id');
                $stmt->execute([':u' => $username, ':r' => $role, ':id' => $id]);
            } else {
                $stmt = $db->prepare('INSERT INTO team_members (username, role, created_at) VALUES (:u, :r, NOW())');
                $stmt->execute([':u' => $username, ':r' => $role]);
            }
        } catch (\Throwable $e) {
            $this->completeAdminAction('/admin?tab=team');
        }

        $this->completeAdminAction('/admin?tab=team');
    }

    public function teamMemberDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=team');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->completeAdminAction('/admin?tab=team');
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM team_members WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
        }

        $this->completeAdminAction('/admin?tab=team');
    }

    public function ipWhitelistAdd(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-whitelist');
        $ipCidr = trim((string)($_POST['ip_cidr'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!$this->isValidIpCidrEntry($ipCidr)) {
            $this->completeAdminAction('/admin?tab=ip-whitelist&err=invalid');
        }

        try {
            $db = Database::connection();
            $dup = $db->prepare('SELECT id FROM ip_whitelist WHERE ip_cidr = :r LIMIT 1');
            $dup->execute([':r' => $ipCidr]);
            if ($dup->fetch()) {
                $this->completeAdminAction('/admin?tab=ip-whitelist&err=dup');
            }

            $stmt = $db->prepare('INSERT INTO ip_whitelist (ip_cidr, reason, created_at) VALUES (:ip_cidr, :reason, NOW())');
            $stmt->execute([
                ':ip_cidr' => $ipCidr,
                ':reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->completeAdminAction('/admin?tab=ip-whitelist&err=invalid');
        }

        $this->completeAdminAction('/admin?tab=ip-whitelist');
    }

    public function ipWhitelistDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-whitelist');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->completeAdminAction('/admin?tab=ip-whitelist');
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM ip_whitelist WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
        }

        $this->completeAdminAction('/admin?tab=ip-whitelist');
    }

    private function isValidIpCidrEntry(string $s): bool
    {
        if ($s === '') {
            return false;
        }

        if (strpos($s, '/') !== false) {
            $parts = explode('/', $s, 2);
            $addr = trim($parts[0]);
            $prefStr = trim($parts[1]);
            if ($prefStr === '' || !ctype_digit($prefStr)) {
                return false;
            }
            $prefix = (int)$prefStr;

            if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $prefix >= 0 && $prefix <= 32;
            }
            if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                return $prefix >= 0 && $prefix <= 128;
            }

            return false;
        }

        return filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    /**
     * 向指定的 QQ 群发送消息 (跨服务器 + Token鉴权版)
     */
    protected static function sendQQGroupMessage(string $message): void
    {
        $apiUrl = defined('QQ_BOT_API_URL') ? (string)QQ_BOT_API_URL : '';
        $groupId = defined('QQ_GROUP_ID') ? (int)QQ_GROUP_ID : 0;
        $token = defined('QQ_BOT_API_TOKEN') ? (string)QQ_BOT_API_TOKEN : '';

        // 如果未配置地址或群号，直接放弃发送，避免网站报错/卡死
        if ($apiUrl === '' || $groupId <= 0) {
            return;
        }

        $endpoint = rtrim($apiUrl, '/') . '/send_group_msg';
        $payload = json_encode([
            'group_id' => $groupId,
            'message' => $message,
            'auto_escape' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            return;
        }

        $headers = [
            "Content-Type: application/json",
        ];

        if ($token !== '') {
            // OneBot V11 / NapCat：Bearer 鉴权（若你的服务端未启用 Token，则可不填）
            $headers[] = "Authorization: Bearer " . $token;
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                // 最多等待 3 秒，防止机器人/网络异常拖累后台请求
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($options);
        @file_get_contents($endpoint, false, $context);
    }
}
