<?php
declare(strict_types=1);

namespace Controller;

use Core\AuthMePassword;
use Core\Controller;
use Core\Database;
use Core\MUAFetcher;
use Core\PasswordHasher;
use Core\PasswordResetService;
use Core\RconClient;
use Model\Feedback;
use Model\User;
use PHPMailer\PHPMailer\PHPMailer;

class ProfileController extends Controller
{
    private User $users;
    private Feedback $feedbacks;

    public function __construct()
    {
        parent::__construct();
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        $this->users = new User();
        $this->feedbacks = new Feedback();
    }

    public function index(): string
    {
        $this->generateCsrfToken();
        $userId = (int)$_SESSION['user_id'];
        $profile = $this->users->getProfile($userId);
        if (!$profile) {
            header('Location: /auth/logout');
            exit;
        }

        $cooldownSeconds = 30 * 24 * 3600;
        $canChangeMcName = true;
        $cooldownMessage = '';
        $remainingSeconds = 0;
        $muaSkinUrl = null;

        $lastBindAt = (string)($profile['last_mc_bind_at'] ?? '');
        if ($lastBindAt !== '') {
            $lastTs = strtotime($lastBindAt);
            if ($lastTs !== false) {
                $remainingSeconds = max(0, ($lastTs + $cooldownSeconds) - time());
                $canChangeMcName = $remainingSeconds <= 0;
                if (!$canChangeMcName) {
                    $remainingDays = (int)ceil($remainingSeconds / 86400);
                    $cooldownMessage = '冷却中：还需 ' . $remainingDays . ' 天可再次修改游戏角色绑定。';
                }
            }
        }

        $muaSub = trim((string)($profile['mua_sub'] ?? ''));
        if ($muaSub !== '') {
            $lookupName = !empty($profile['mc_username']) ? (string)$profile['mc_username'] : (string)$profile['username'];
            try {
                $muaSkinUrl = (new \Core\MUAFetcher())->getSkinDirectUrl($lookupName, $muaSub);
            } catch (\Throwable $e) {
                error_log('MUA Skin Fetch Failed: ' . $e->getMessage());
            }
        }

        $feedbackList = [];
        $feedbackLoadError = '';
        try {
            $feedbackList = $this->feedbacks->getUserFeedbackList($userId, 50);
        } catch (\Throwable $e) {
            $feedbackLoadError = '举报反馈功能暂不可用，请联系管理员执行数据库升级。';
            if (APP_ENV === 'development') {
                $feedbackLoadError .= ' (' . $e->getMessage() . ')';
            }
        }

        $feedbackFlash = $_SESSION['profile_feedback_flash'] ?? null;
        unset($_SESSION['profile_feedback_flash']);
        if (!is_array($feedbackFlash) || !isset($feedbackFlash['type'], $feedbackFlash['message'])) {
            $feedbackFlash = null;
        }

        return $this->render('user/profile', [
            'title' => '个人中心',
            'profile' => $profile,
            'muaSkinUrl' => $muaSkinUrl,
            'canChangeMcName' => $canChangeMcName,
            'cooldownMessage' => $cooldownMessage,
            'emailMasked' => $this->maskEmail((string)($profile['email'] ?? '')),
            'quickResetCooldownSeconds' => 60,
            'remainingCooldownSeconds' => $remainingSeconds,
            'feedbackList' => $feedbackList,
            'feedbackLoadError' => $feedbackLoadError,
            'feedbackFlash' => $feedbackFlash,
        ]);
    }

    public function feedbackCreate(): void
    {
        $isAjax = $this->isAjaxRequest() || array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->completeFeedbackCreate(false, '请先登录后再提交反馈。', 401, $isAjax);
            return;
        }

        $this->generateCsrfToken();
        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $this->completeFeedbackCreate(false, 'CSRF 校验失败，请刷新后重试。', 403, $isAjax);
            return;
        }

        $profile = $this->users->getProfile($userId);
        if (!$profile) {
            $this->completeFeedbackCreate(false, '用户不存在，请重新登录。', 404, $isAjax);
            return;
        }

        $category = strtolower(trim((string)($_POST['category'] ?? 'other')));
        if (!in_array($category, Feedback::allowedCategories(), true)) {
            $this->completeFeedbackCreate(false, '反馈类型不合法。', 400, $isAjax);
            return;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $titleLength = mb_strlen($title, 'UTF-8');
        if ($titleLength < 3 || $titleLength > 120) {
            $this->completeFeedbackCreate(false, '标题长度需在 3-120 字之间。', 400, $isAjax);
            return;
        }

        $content = trim((string)($_POST['content'] ?? ''));
        $contentLength = mb_strlen($content, 'UTF-8');
        if ($contentLength < 10 || $contentLength > 5000) {
            $this->completeFeedbackCreate(false, '详细内容长度需在 10-5000 字之间。', 400, $isAjax);
            return;
        }

        $targetPlayer = trim((string)($_POST['target_player'] ?? ''));
        if ($targetPlayer !== '') {
            if (mb_strlen($targetPlayer, 'UTF-8') > 64) {
                $this->completeFeedbackCreate(false, '被举报玩家名称过长。', 400, $isAjax);
                return;
            }
            if (preg_match('/^[a-zA-Z0-9_]{1,64}$/', $targetPlayer) !== 1) {
                $this->completeFeedbackCreate(false, '被举报玩家名称格式不合法。', 400, $isAjax);
                return;
            }
        }

        $world = trim((string)($_POST['world'] ?? ''));
        if ($world !== '') {
            if (mb_strlen($world, 'UTF-8') > 64) {
                $this->completeFeedbackCreate(false, '世界名称过长。', 400, $isAjax);
                return;
            }
            if (preg_match('/^[\p{L}\p{N}_\-\s\.]{1,64}$/u', $world) !== 1) {
                $this->completeFeedbackCreate(false, '世界名称格式不合法。', 400, $isAjax);
                return;
            }
        }

        $coordinates = trim((string)($_POST['coordinates'] ?? ''));
        if ($coordinates !== '') {
            if (mb_strlen($coordinates, 'UTF-8') > 64) {
                $this->completeFeedbackCreate(false, '坐标内容过长。', 400, $isAjax);
                return;
            }
            if (preg_match('/^[a-zA-Z0-9,\-\+\.\s~:]{1,64}$/', $coordinates) !== 1) {
                $this->completeFeedbackCreate(false, '坐标格式不合法。', 400, $isAjax);
                return;
            }
        }

        $evidenceUrl = trim((string)($_POST['evidence_url'] ?? ''));
        if ($evidenceUrl !== '') {
            if (mb_strlen($evidenceUrl, 'UTF-8') > 500) {
                $this->completeFeedbackCreate(false, '证据链接长度不能超过 500 字符。', 400, $isAjax);
                return;
            }

            $validatedUrl = filter_var($evidenceUrl, FILTER_VALIDATE_URL);
            $scheme = strtolower((string)parse_url((string)$validatedUrl, PHP_URL_SCHEME));
            if ($validatedUrl === false || !in_array($scheme, ['http', 'https'], true)) {
                $this->completeFeedbackCreate(false, '证据链接必须为 http 或 https 地址。', 400, $isAjax);
                return;
            }
            $evidenceUrl = (string)$validatedUrl;
        }

        $occurredAtInput = trim((string)($_POST['occurred_at'] ?? ''));
        $occurredAt = null;
        if ($occurredAtInput !== '') {
            $occurredAt = $this->normalizeOptionalDatetime($occurredAtInput);
        }

        $feedbackData = [
            'user_id' => $userId,
            'username' => (string)($profile['username'] ?? ''),
            'mc_username' => (string)($profile['mc_username'] ?? ''),
            'category' => $category,
            'target_player' => $targetPlayer,
            'title' => $title,
            'content' => $content,
            'world' => $world,
            'coordinates' => $coordinates,
            'occurred_at' => $occurredAt,
            'evidence_url' => $evidenceUrl,
            'created_ip' => $this->getClientIp(),
        ];

        try {
            $feedbackId = $this->feedbacks->createFeedback($feedbackData, $_FILES['attachments'] ?? []);
            $this->completeFeedbackCreate(
                true,
                '反馈已提交，请等待管理员处理',
                200,
                $isAjax,
                ['feedback_id' => $feedbackId]
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : '反馈提交失败，请稍后再试。';
            $this->completeFeedbackCreate(false, $message, 400, $isAjax);
        }
    }

    public function feedbackSupplement(): void
    {
        $isAjax = $this->isAjaxRequest() || array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->completeFeedbackCreate(false, '请先登录后再补充反馈。', 401, $isAjax);
            return;
        }

        $this->generateCsrfToken();
        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            $this->completeFeedbackCreate(false, 'CSRF 校验失败，请刷新后重试。', 403, $isAjax);
            return;
        }

        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        if ($feedbackId <= 0) {
            $this->completeFeedbackCreate(false, '反馈编号无效。', 400, $isAjax);
            return;
        }

        $supplementContent = trim((string)($_POST['supplement_content'] ?? ''));
        $supplementLength = mb_strlen($supplementContent, 'UTF-8');
        if ($supplementLength < 10 || $supplementLength > 5000) {
            $this->completeFeedbackCreate(false, '补充说明长度需在 10-5000 字之间。', 400, $isAjax);
            return;
        }

        try {
            $feedback = $this->feedbacks->getUserFeedbackById($feedbackId, $userId);
            if (!$feedback) {
                $this->completeFeedbackCreate(false, '反馈不存在或无权限补充。', 404, $isAjax);
                return;
            }

            $status = strtolower(trim((string)($feedback['status'] ?? '')));
            if ($status !== 'need_more_info') {
                $this->completeFeedbackCreate(false, '仅“需要补充”状态的反馈可提交补充材料。', 400, $isAjax);
                return;
            }

            $updated = $this->feedbacks->appendUserSupplement(
                $feedbackId,
                $userId,
                $supplementContent,
                $_FILES['attachments'] ?? []
            );
            if (!$updated) {
                $this->completeFeedbackCreate(false, '补充提交失败，请稍后重试。', 400, $isAjax);
                return;
            }

            $this->completeFeedbackCreate(
                true,
                '补充材料已提交，状态已更新为处理中。',
                200,
                $isAjax,
                ['feedback_id' => $feedbackId, 'status' => 'reviewing']
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : '补充提交失败，请稍后重试。';
            $this->completeFeedbackCreate(false, $message, 400, $isAjax);
        }
    }

    public function updatePassword(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $oldPassword = (string)($_POST['old_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        if ($userId <= 0 || $oldPassword === '' || $newPassword === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }
        if (mb_strlen($newPassword) < 6) {
            $this->json(['success' => false, 'message' => '新密码至少需要 6 位'], 400);
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $storedHash = is_array($row) ? (string)($row['password_hash'] ?? '') : '';
        $isModernHash = PasswordHasher::isModernHash($storedHash);
        $oldPasswordValid = false;
        if (is_array($row)) {
            if ($isModernHash) {
                $oldPasswordValid = PasswordHasher::verify($oldPassword, $storedHash);
            } else {
                $oldPasswordValid = AuthMePassword::verify($oldPassword, $storedHash);
            }
        }

        if (!$row || !$oldPasswordValid) {
            $this->json(['success' => false, 'message' => '旧密码不正确'], 400);
            return;
        }

        $newHash = PasswordHasher::hash($newPassword);
        $this->users->updatePassword($userId, $newHash);

        try {
            $this->users->deleteRememberTokensByUserId($userId);
        } catch (\Throwable $e) {
        }

        $this->clearRememberMeCookie();
        $this->regenerateSessionIdSafely();
        $this->json(['success' => true, 'message' => '密码修改成功']);
    }

    public function updateMinecraftCharacter(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $mcName = trim((string)($_POST['mc_name'] ?? ''));
        if ($userId <= 0 || $mcName === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        $profile = $this->users->getProfile($userId);
        if (!$profile) {
            $this->json(['success' => false, 'message' => '用户不存在'], 404);
            return;
        }

        $lastBindAt = (string)($profile['last_mc_bind_at'] ?? '');
        if ($lastBindAt !== '') {
            $lastTs = strtotime($lastBindAt);
            if ($lastTs !== false) {
                $remaining = ($lastTs + (30 * 24 * 3600)) - time();
                if ($remaining > 0) {
                    $days = (int)ceil($remaining / 86400);
                    $this->json(['success' => false, 'message' => '冷却中，还需 ' . $days . ' 天后才能修改'], 429);
                    return;
                }
            }
        }

        $mcUuid = trim((string)($profile['mc_uuid'] ?? ''));
        if ($mcUuid === '') {
            $this->json([
                'success' => false,
                'message' => 'Please bind Minecraft account through a verified flow (Microsoft OAuth) before updating character name.',
            ], 403);
            return;
        }

        // Security hardening: keep verified UUID unchanged, only update display name.
        $this->users->updateMinecraftNameWithCooldown($userId, $mcName);

        $muaSub = trim((string)($profile['mua_sub'] ?? ''));
        if ($muaSub !== '' && defined('RCON_PASSWORD') && trim((string)RCON_PASSWORD) !== '') {
            try {
                $muaSkinUrl = (new MUAFetcher())->getSkinDirectUrl($mcName, $muaSub);
                if (is_string($muaSkinUrl) && $muaSkinUrl !== '') {
                    $skinAlias = 'mua_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $muaSub);
                    if ($skinAlias !== 'mua_') {
                        $rcon = new RconClient((string)RCON_HOST, (int)RCON_PORT, (string)RCON_PASSWORD, (float)RCON_TIMEOUT);
                        $rcon->sendCommand('sr createcustom ' . $skinAlias . ' ' . $muaSkinUrl);
                        $rcon->sendCommand(
                            'sr set ' . escapeshellarg($mcName) . ' ' . escapeshellarg($skinAlias)
                        );
                    }
                }
            } catch (\Throwable $e) {
                // Do not break profile update flow if external sync fails.
            }
        }

        $this->json(['success' => true, 'message' => '游戏角色绑定更新成功', 'mc_uuid' => $mcUuid]);
    }

    public function sendQuickResetEmail(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['success' => false, 'message' => '未登录'], 401);
            return;
        }

        $sessionKey = 'profile_quick_reset_last_sent_at_' . $userId;
        $now = time();
        $lastSent = isset($_SESSION[$sessionKey]) ? (int)$_SESSION[$sessionKey] : 0;
        if ($lastSent > 0 && ($now - $lastSent) < 60) {
            $this->json(['success' => false, 'message' => '请求过于频繁，请 60 秒后再试'], 429);
            return;
        }

        $profile = $this->users->getProfile($userId);
        $email = trim((string)($profile['email'] ?? ''));
        if ($email === '') {
            $this->json(['success' => false, 'message' => '未绑定邮箱，无法发送重置链接'], 400);
            return;
        }

        try {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }

            $db = Database::connection();
            $token = PasswordResetService::issueTokenForEmail($db, $email);
            $resetUrl = PasswordResetService::buildResetUrl($token);

            $username = (string)($profile['username'] ?? '玩家');
            $subject = '繁星World 一键密码重置';
            $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#111827;">'
                . '<h2>你好，' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</h2>'
                . '<p>你在个人中心发起了一键重置密码请求，请点击下方链接（1 小时有效）：</p>'
                . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
                . '<p>如果不是你本人操作，请忽略此邮件。</p>'
                . '</body></html>';

            $mail = new PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = (SMTP_PORT === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(SMTP_FROM_EMAIL, '繁星World');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            $_SESSION[$sessionKey] = $now;
            $this->json(['success' => true, 'message' => '重置链接已发送，请查收邮箱']);
        } catch (\Throwable $e) {
            if (APP_ENV === 'development') {
                $this->json(['success' => false, 'message' => '系统错误: ' . $e->getMessage()], 500);
            } else {
                $this->json(['success' => false, 'message' => '系统服务异常，请联系管理员'], 500);
            }
        }
    }

    private function normalizeOptionalDatetime(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $input);
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $normalized);
        if (!$dt instanceof \DateTime) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i', $normalized);
        }

        if (!$dt instanceof \DateTime) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function completeFeedbackCreate(
        bool $success,
        string $message,
        int $statusCode,
        bool $isAjax,
        array $payload = []
    ): void {
        if ($isAjax) {
            $response = array_merge(
                [
                    'success' => $success,
                    'message' => $message,
                ],
                $payload
            );
            $this->json($response, $statusCode);
            return;
        }

        $_SESSION['profile_feedback_flash'] = [
            'type' => $success ? 'success' : 'error',
            'message' => $message,
        ];
        header('Location: /profile?tab=feedback');
        exit;
    }

    private function maskEmail(string $email): string
    {
        if ($email === '' || strpos($email, '@') === false) {
            return '未绑定邮箱';
        }
        [$name, $domain] = explode('@', $email, 2);
        $len = mb_strlen($name);
        if ($len <= 1) {
            return '*' . '@' . $domain;
        }
        if ($len === 2) {
            return mb_substr($name, 0, 1) . '*' . '@' . $domain;
        }
        return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1) . '@' . $domain;
    }
}
