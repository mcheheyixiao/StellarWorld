<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Database;
use Core\MUAFetcher;
use Core\MinecraftUuid;
use Core\RconClient;
use Model\User;
use PHPMailer\PHPMailer\PHPMailer;

class ProfileController extends Controller
{
    private User $users;

    public function __construct()
    {
        parent::__construct();
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        $this->users = new User();
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

        return $this->render('user/profile', [
            'title' => '个人中心',
            'profile' => $profile,
            'muaSkinUrl' => $muaSkinUrl,
            'canChangeMcName' => $canChangeMcName,
            'cooldownMessage' => $cooldownMessage,
            'emailMasked' => $this->maskEmail((string)($profile['email'] ?? '')),
            'quickResetCooldownSeconds' => 60,
            'remainingCooldownSeconds' => $remainingSeconds,
        ]);
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
        if (!$row || !$this->verifyAuthMeHash($oldPassword, (string)$row['password_hash'])) {
            $this->json(['success' => false, 'message' => '旧密码不正确'], 400);
            return;
        }

        $newHash = $this->generateAuthMeHash($newPassword);
        $this->users->updatePassword($userId, $newHash);
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

        $mcUuid = MinecraftUuid::getOfflineUuid($mcName);
        $this->users->updateMinecraftBindWithCooldown($userId, $mcUuid, $mcName);

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

            $token = bin2hex(random_bytes(32));
            $db = Database::connection();

            $del = $db->prepare('DELETE FROM password_resets WHERE email = :email');
            $del->execute([':email' => $email]);

            $ins = $db->prepare('
                INSERT INTO password_resets (email, token, created_at)
                VALUES (:email, :token, NOW())
            ');
            $ins->execute([
                ':email' => $email,
                ':token' => $token,
            ]);

            $resetUrl = sprintf(
                '%s://%s/reset-password?token=%s',
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http',
                $_SERVER['HTTP_HOST'] ?? 'localhost',
                urlencode($token)
            );

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

    private function generateAuthMeHash(string $password): string
    {
        $salt = substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, 16);
        $hash = hash('sha256', hash('sha256', $password) . $salt);
        return '$SHA$' . $salt . '$' . $hash;
    }

    private function verifyAuthMeHash(string $password, string $hash): bool
    {
        $parts = explode('$', $hash);
        if (count($parts) === 4 && $parts[1] === 'SHA') {
            $salt = $parts[2];
            $validHash = hash('sha256', hash('sha256', $password) . $salt);
            return hash_equals($validHash, $parts[3]);
        }
        return false;
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
