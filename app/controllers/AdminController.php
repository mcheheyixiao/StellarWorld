<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Database;
use Core\ImageProcessor;
use Core\MinecraftUuid;
use Model\User;

class AdminController extends Controller
{
    private User $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new User();
        $this->requireAdmin();
    }

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            header('Location: /auth/login');
            exit;
        }
    }

    /**
     * @return array{
     *   enable_realtime_panel: bool,
     *   ws_url: string,
     *   ws_auth_token: string,
     *   reconnect_interval_ms: int
     * }
     */
    private function getRealtimePanelConfig(): array
    {
        $enabled = defined('REALTIME_ENABLE_PANEL') ? (bool)REALTIME_ENABLE_PANEL : false;
        $wsUrl = defined('REALTIME_WS_URL') ? trim((string)REALTIME_WS_URL) : '';
        $authToken = defined('REALTIME_WS_AUTH_TOKEN') ? (string)REALTIME_WS_AUTH_TOKEN : '';
        $reconnectIntervalMs = defined('REALTIME_RECONNECT_INTERVAL_MS') ? (int)REALTIME_RECONNECT_INTERVAL_MS : 3000;

        return [
            'enable_realtime_panel' => $enabled,
            'ws_url' => $wsUrl,
            'ws_auth_token' => $authToken,
            'reconnect_interval_ms' => max(500, $reconnectIntervalMs),
        ];
    }

    public function realtime(): void
    {
        $config = $this->getRealtimePanelConfig();
        if (($config['enable_realtime_panel'] ?? false) !== true) {
            header('Location: /admin?tab=dashboard');
            exit;
        }

        header('Location: /admin?tab=realtime');
        exit;
    }

    public function dashboard(): string
    {
        $this->generateCsrfToken();
        $realtimeWsConfig = $this->getRealtimePanelConfig();
        $db = Database::connection();
        $userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $announcementCount = (int)$db->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
        $galleryCount = (int)$db->query('SELECT COUNT(*) FROM gallery_images')->fetchColumn();
        $players = $db->query('SELECT id, username, mc_username, mc_uuid, email, role, status, created_at, ip, regip, lastlogin, regdate FROM users ORDER BY created_at DESC')->fetchAll() ?: [];
        $announcements = $db->query('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll() ?: [];
        $milestones = $db->query('SELECT * FROM milestones ORDER BY created_at DESC, id DESC')->fetchAll() ?: [];
        $images = $db->query('SELECT * FROM gallery_images ORDER BY created_at DESC')->fetchAll() ?: [];

        $ipBlacklist = [];
        try {
            $ipBlacklist = $db->query('SELECT id, ip_cidr, reason, created_at FROM ip_blacklist ORDER BY id DESC')->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $ipBlacklist = [];
        }

        $siteSettings = [];
        try {
            $siteSettings = $db->query('SELECT id, setting_key, setting_value, description FROM site_settings ORDER BY id ASC')->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $siteSettings = [];
        }

        $teamMembers = [];
        try {
            $teamMembers = $db->query('SELECT id, username, role, created_at FROM team_members ORDER BY id ASC')->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $teamMembers = [];
        }

        $ipWhitelist = [];
        try {
            $ipWhitelist = $db->query('SELECT id, ip_cidr, reason, created_at FROM ip_whitelist ORDER BY id DESC')->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $ipWhitelist = [];
        }

        return $this->render('admin/dashboard', [
            'title' => '后台总览',
            'userCount' => $userCount,
            'announcementCount' => $announcementCount,
            'galleryCount' => $galleryCount,
            'players' => $players,
            'announcements' => $announcements,
            'milestones' => $milestones,
            'images' => $images,
            'ipBlacklist' => $ipBlacklist,
            'siteSettings' => $siteSettings,
            'teamMembers' => $teamMembers,
            'ipWhitelist' => $ipWhitelist,
            'realtimePanelEnabled' => (bool)$realtimeWsConfig['enable_realtime_panel'],
            'realtimeWsConfig' => $realtimeWsConfig,
        ]);
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

        header('Location: /admin?tab=players');
        exit;
    }

    public function playerDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=players');
        $id = (int)($_POST['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        header('Location: /admin?tab=players');
        exit;
    }

    public function playerUnbind(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=players');
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->users->unbindCharacter($id);
        }
        header('Location: /admin?tab=players');
        exit;
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
                    $baiduApiUrl = 'http://data.zz.baidu.com/urls?site=' . urlencode(SITE_BASE_URL) . '&token=' . urlencode(BAIDU_PUSH_TOKEN);
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

        header('Location: /admin?tab=announcements');
        exit;
    }

    public function announcementDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=announcements');
        $id = (int)($_POST['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute([':id' => $id]);
        header('Location: /admin?tab=announcements');
        exit;
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

        header('Location: /admin?tab=milestones');
        exit;
    }

    public function milestoneDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=milestones');
        $id = (int)($_POST['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM milestones WHERE id = :id');
        $stmt->execute([':id' => $id]);
        header('Location: /admin?tab=milestones');
        exit;
    }

    public function galleryUpload(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=gallery');
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            header('Location: /admin/gallery');
            exit;
        }

        $tmp = $_FILES['image']['tmp_name'];
        $name = basename((string)$_FILES['image']['name']);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destRel = '/uploads/gallery/' . $safeName;
        $destAbs = PUBLIC_PATH . $destRel;

        if (!is_dir(dirname($destAbs))) {
            @mkdir(dirname($destAbs), 0775, true);
        }

        if (!move_uploaded_file($tmp, $destAbs)) {
            header('Location: /admin/gallery');
            exit;
        }

        ImageProcessor::generateGalleryResponsiveSet($destAbs);

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO gallery_images (title, description, image_path, created_at) VALUES (:title, :description, :image_path, NOW())');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':image_path' => $destRel,
        ]);

        header('Location: /admin?tab=gallery');
        exit;
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
        header('Location: /admin?tab=gallery');
        exit;
    }

    public function ipBlacklistAdd(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-blacklist');
        $ipCidr = trim((string)($_POST['ip_cidr'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!$this->isValidIpCidrEntry($ipCidr)) {
            header('Location: /admin?tab=ip-blacklist&err=invalid');
            exit;
        }

        $db = Database::connection();
        $dup = $db->prepare('SELECT id FROM ip_blacklist WHERE ip_cidr = :r LIMIT 1');
        $dup->execute([':r' => $ipCidr]);
        if ($dup->fetch()) {
            header('Location: /admin?tab=ip-blacklist&err=dup');
            exit;
        }

        $stmt = $db->prepare('INSERT INTO ip_blacklist (ip_cidr, reason, created_at) VALUES (:ip_cidr, :reason, NOW())');
        $stmt->execute([
            ':ip_cidr' => $ipCidr,
            ':reason' => $reason,
        ]);

        header('Location: /admin?tab=ip-blacklist');
        exit;
    }

    public function ipBlacklistDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-blacklist');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin?tab=ip-blacklist');
            exit;
        }

        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM ip_blacklist WHERE id = :id');
        $stmt->execute([':id' => $id]);

        header('Location: /admin?tab=ip-blacklist');
        exit;
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
            header('Location: /admin?tab=site-settings');
            exit;
        }

        $settingsIn = $_POST['settings'] ?? [];
        if (!is_array($settingsIn)) {
            header('Location: /admin?tab=site-settings');
            exit;
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
            }
            $upd->execute([':v' => $val, ':k' => $key]);
        }

        header('Location: /admin?tab=site-settings');
        exit;
    }

    public function teamMemberSave(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=team');
        $id = (int)($_POST['id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));

        if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{1,32}$/', $username)) {
            header('Location: /admin?tab=team&err=invalid_user');
            exit;
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
            header('Location: /admin?tab=team');
            exit;
        }

        header('Location: /admin?tab=team');
        exit;
    }

    public function teamMemberDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=team');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin?tab=team');
            exit;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM team_members WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
        }

        header('Location: /admin?tab=team');
        exit;
    }

    public function ipWhitelistAdd(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-whitelist');
        $ipCidr = trim((string)($_POST['ip_cidr'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!$this->isValidIpCidrEntry($ipCidr)) {
            header('Location: /admin?tab=ip-whitelist&err=invalid');
            exit;
        }

        try {
            $db = Database::connection();
            $dup = $db->prepare('SELECT id FROM ip_whitelist WHERE ip_cidr = :r LIMIT 1');
            $dup->execute([':r' => $ipCidr]);
            if ($dup->fetch()) {
                header('Location: /admin?tab=ip-whitelist&err=dup');
                exit;
            }

            $stmt = $db->prepare('INSERT INTO ip_whitelist (ip_cidr, reason, created_at) VALUES (:ip_cidr, :reason, NOW())');
            $stmt->execute([
                ':ip_cidr' => $ipCidr,
                ':reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            header('Location: /admin?tab=ip-whitelist&err=invalid');
            exit;
        }

        header('Location: /admin?tab=ip-whitelist');
        exit;
    }

    public function ipWhitelistDelete(): void
    {
        $this->validateCsrfForFormPost('/admin?tab=ip-whitelist');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /admin?tab=ip-whitelist');
            exit;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM ip_whitelist WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
        }

        header('Location: /admin?tab=ip-whitelist');
        exit;
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

