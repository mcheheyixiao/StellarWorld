<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\MUAFetcher;
use Core\RconClient;
use Core\MinecraftUuid;
use Model\User;
use Model\AuditModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController extends Controller
{
    private User $users;
    private AuditModel $audit;

    public function __construct()
    {
        parent::__construct();
        $this->users = new User();
        $this->audit = new AuditModel();
    }

    public function showLogin(): string
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $this->generateCsrfToken();
        return $this->render('auth/login', [
            'title' => '玩家登录',
        ]);
    }

    public function showRegister(): string
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $this->generateCsrfToken();
        return $this->render('auth/register', [
            'title' => '玩家注册',
            'oauthPendingEmail' => (string)($_SESSION['oauth_pending_user']['email'] ?? ''),
        ]);
    }

    public function muaRedirect(): void
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['mua_oauth_state'] = $state;

        $query = http_build_query([
            'client_id' => (string)MUA_CLIENT_ID,
            'redirect_uri' => (string)MUA_REDIRECT_URI,
            'response_type' => 'code',
            'state' => $state,
        ]);
        header('Location: https://skin.mualliance.ltd/api/union/oauth2/authorize?' . $query);
        exit;
    }

    public function muaCallback(): void
    {
        try {
            $state = trim((string)($_GET['state'] ?? ''));
            $sessionState = (string)($_SESSION['mua_oauth_state'] ?? '');
            unset($_SESSION['mua_oauth_state']);

            if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
                throw new \RuntimeException('OAuth state 校验失败');
            }

            $code = trim((string)($_GET['code'] ?? ''));
            if ($code === '') {
                throw new \RuntimeException('OAuth 授权码缺失');
            }

            $tokenData = $this->muaRequestToken($code);
            $accessToken = (string)($tokenData['access_token'] ?? '');
            if ($accessToken === '') {
                throw new \RuntimeException('MUA access token 获取失败');
            }

            $muaUser = $this->muaRequestUser($accessToken);
            $sub = trim((string)($muaUser['sub'] ?? ''));
            $email = trim((string)($muaUser['email'] ?? ''));
            $nickname = trim((string)($muaUser['nickname'] ?? ''));

            if ($sub === '' || $email === '') {
                throw new \RuntimeException('MUA 返回用户信息不完整');
            }

            $boundBySub = $this->users->findByMuaSub($sub);
            if ($boundBySub) {
                $this->syncMuaSkinToGameByUser((int)$boundBySub['id']);
                $this->signInUser($boundBySub);
                header('Location: /');
                exit;
            }

            $boundByEmail = $this->users->findByEmail($email);
            if ($boundByEmail) {
                $this->users->bindMuaSub((int)$boundByEmail['id'], $sub);
                $this->syncMuaSkinToGameByUser((int)$boundByEmail['id']);
                $boundByEmail['mua_sub'] = $sub;
                $this->signInUser($boundByEmail);
                header('Location: /');
                exit;
            }

            $_SESSION['oauth_pending_user'] = [
                'provider' => 'mua',
                'sub' => $sub,
                'email' => $email,
                'nickname' => $nickname,
                'issued_at' => time(),
            ];
            header('Location: /auth/register');
            exit;
        } catch (\Throwable $e) {
            error_log('MUA OAuth callback error: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'OAuth回调处理失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showForgotPassword(): string
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $this->generateCsrfToken();
        return $this->render('auth/forgot_password', [
            'title' => '找回密码',
        ]);
    }

    public function sendResetLink(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $ip = $this->getClientIp();
        $isWhite = $this->isIpWhitelisted($ip);
        $block = !$isWhite ? $this->findIpBlacklistMatch($ip) : null;
        if ($block !== null) {
            try {
                $this->audit->logAction(null, 'IP_BANNED_BLOCKED', $ip, [
                    'route' => '/forgot-password',
                    'reason' => $block['reason'],
                    'matched_rule' => $block['matched_rule'],
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '访问被拒绝'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $this->checkRateLimit('forgot_password|' . $ip, 5, 3600);
        }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        // === 新增：Turnstile 人机验证（找回密码强制） ===
        $tsResp = trim((string)($_POST['cf-turnstile-response'] ?? ''));
        if ($tsResp === '' || !$this->verifyTurnstile($tsResp, $ip)) {
            $this->json(['success' => false, 'message' => '人机验证失败，请重试'], 403);
            return;
        }

        // === 新增：Session 轻量级冷却限流（无数据库）===
        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $cooldown = defined('AUTH_ACTION_COOLDOWN') ? (int)AUTH_ACTION_COOLDOWN : 60;
            $last = isset($_SESSION['last_auth_action']) ? (int)$_SESSION['last_auth_action'] : 0;
            if ($last > 0 && (time() - $last) < $cooldown) {
                $this->json(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
                return;
            }
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->json(['success' => false, 'message' => '该邮箱未注册'], 404);
            return;
        }

        try {
            // 兼容要求：显式加载 PHPMailer（通常项目入口已加载 Composer Autoload）
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }

            $token = bin2hex(random_bytes(32));
            $db = \Core\Database::connection();

            // 单邮箱仅保留一条有效记录，避免重复点击造成多条 token
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

            $resetUrl = sprintf('%s://%s/reset-password?token=%s',
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http',
                $_SERVER['HTTP_HOST'] ?? 'localhost',
                urlencode($token)
            );

            $subject = '繁星World 密码重置';
            $username = trim((string)($user['username'] ?? ''));
            if ($username === '') {
                $username = '玩家';
            }

            // TODO: 请将此处的 LOGO_URL 替换为您真实的图片链接
            $template = '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 10px;background:#0f172a;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111827;border-radius:14px;overflow:hidden;border:1px solid #1f2937;">
<tr><td align="center" style="padding:30px;background:#020617;">
<img src="LOGO_URL" width="80" style="display:block;margin-bottom:10px;">
<h2 style="margin:0;color:#38bdf8;">SERVER_NAME</h2>
</td></tr>
<tr><td style="padding:40px 35px;text-align:center;">
<h1 style="margin-top:0;font-size:24px;">重置你的密码 🔑</h1>
<p style="color:#cbd5f5;font-size:15px;line-height:1.6;">你好 <b>USERNAME</b>，<br>我们收到了一个重置密码的请求。<br>点击下面按钮即可设置新的密码。</p>
<table cellpadding="0" cellspacing="0" align="center" style="margin-top:30px;"><tr><td align="center" bgcolor="#3b82f6" style="border-radius:8px;">
<a href="RESET_LINK" style="display:inline-block;padding:14px 30px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:16px;">重置密码</a>
</td></tr></table>
<p style="margin-top:30px;color:#94a3b8;font-size:13px;">如果按钮无法点击，请复制下面链接：</p>
<p style="word-break:break-all;color:#38bdf8;font-size:13px;">RESET_LINK</p>
<p style="margin-top:25px;color:#ef4444;font-size:13px;">如果你没有请求重置密码，可以忽略此邮件。</p>
</td></tr>
<tr><td style="padding:25px;text-align:center;background:#020617;color:#64748b;font-size:12px;">© 2026 SERVER_NAME<br>Minecraft Server Network</td></tr>
</table></td></tr></table></body></html>';

            $body = str_replace(
                ['LOGO_URL', 'SERVER_NAME', 'USERNAME', 'RESET_LINK'],
                ['https://www.stellarvan.cn/images/logo.png', '繁星World', htmlspecialchars($username, ENT_QUOTES, 'UTF-8'), htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8')],
                $template
            );

            $mail = new PHPMailer(true);
            // 禁用 SMTP 调试输出，避免破坏 JSON 响应
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
            $mail->Body    = $body;

            $mail->send();

            // === 新增：找回密码成功后写入冷却时间 ===
            $_SESSION['last_auth_action'] = time();
            $this->json(['success' => true, 'message' => '重置链接已发送，请查收邮箱']);
        } catch (\Throwable $e) {
            if (APP_ENV === 'development') {
                $this->json(['success' => false, 'message' => '系统错误: ' . $e->getMessage()], 500);
            } else {
                $this->json(['success' => false, 'message' => '系统服务异常，请联系管理员'], 500);
            }
        }
    }

    public function showResetPassword(): string
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        $this->generateCsrfToken();
        $token = trim((string)($_GET['token'] ?? ''));
        $valid = false;
        $message = '';

        if ($token === '') {
            $message = '重置链接无效或已过期';
        } else {
            $db = \Core\Database::connection();
            $stmt = $db->prepare('
                SELECT email
                FROM password_resets
                WHERE token = :token
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LIMIT 1
            ');
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch();
            if ($row) {
                $valid = true;
            } else {
                $message = '重置链接无效或已过期（有效期 1 小时）';
            }
        }

        return $this->render('auth/reset_password', [
            'title' => '重置密码',
            'token' => $token,
            'valid' => $valid,
            'message' => $message,
        ]);
    }

    public function updatePassword(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $ip = $this->getClientIp();
        $isWhite = $this->isIpWhitelisted($ip);
        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $this->checkRateLimit('auth_reset_password|' . $ip, 15, 3600);
        }

        $token = trim((string)($_POST['token'] ?? ''));
        $passwordRaw = (string)($_POST['password'] ?? '');

        if ($token === '' || $passwordRaw === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        $db = \Core\Database::connection();
        $stmt = $db->prepare('
            SELECT email
            FROM password_resets
            WHERE token = :token
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            LIMIT 1
        ');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        if (!$row || empty($row['email'])) {
            $this->json(['success' => false, 'message' => '重置链接无效或已过期'], 400);
            return;
        }

        $email = (string)$row['email'];
        $hash = $this->generateAuthMeHash($passwordRaw);

        $upd = $db->prepare('UPDATE users SET password_hash = :hash WHERE email = :email LIMIT 1');
        $upd->execute([
            ':hash' => $hash,
            ':email' => $email,
        ]);

        $del = $db->prepare('DELETE FROM password_resets WHERE token = :token');
        $del->execute([':token' => $token]);

        try {
            $user = $this->users->findByEmail($email);
            if (is_array($user) && isset($user['id'])) {
                $this->users->deleteRememberTokensByUserId((int)$user['id']);
            }
        } catch (\Throwable $e) {
        }

        $this->clearRememberMeCookie();
        $this->regenerateSessionIdSafely();

        $this->json(['success' => true, 'message' => '密码已更新，请使用新密码登录']);
    }

    public function login(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $ip = $this->getClientIp();
        $isWhite = $this->isIpWhitelisted($ip);
        $block = !$isWhite ? $this->findIpBlacklistMatch($ip) : null;
        if ($block !== null) {
            try {
                $this->audit->logAction(null, 'IP_BANNED_BLOCKED', $ip, [
                    'route' => '/auth/login',
                    'reason' => $block['reason'],
                    'matched_rule' => $block['matched_rule'],
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '访问被拒绝'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $this->checkRateLimit('auth_login|' . $ip, 25, 600);
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $passwordRaw = (string)($_POST['password'] ?? '');
        $mcUuid = trim((string)($_POST['mc_uuid'] ?? '')); // legacy support
        $mcName = trim((string)($_POST['mc_name'] ?? '')); // legacy support
        $mcUsername = trim((string)($_POST['mc_username'] ?? ''));
        $pendingOAuth = $_SESSION['oauth_pending_user'] ?? null;
        $isMuaPending = is_array($pendingOAuth)
            && (($pendingOAuth['provider'] ?? '') === 'mua')
            && !empty($pendingOAuth['sub'])
            && !empty($pendingOAuth['email']);

        if ($username === '' || $passwordRaw === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        // === 新增：Turnstile 人机验证（登录强制，移除旧版兼容）===
        $tsResp = trim((string)($_POST['cf-turnstile-response'] ?? ''));
        if ($tsResp === '' || !$this->verifyTurnstile($tsResp, $ip)) {
            $this->json(['success' => false, 'message' => '人机验证失败，请重试'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite) && $this->isLoginLocked($ip, $username)) {
            $this->json(['success' => false, 'message' => '尝试次数过多，请稍后再试'], 429);
            return;
        }

        $user = $this->users->findByUsername($username);
        if (!$user || !$this->verifyAuthMeHash($passwordRaw, (string)$user['password_hash'])) {
            $this->recordLoginAttempt($ip, $username, false);
            $this->json(['success' => false, 'message' => '用户名或密码错误'], 401);
            return;
        }

        if ($user['status'] !== 'active') {
            $this->json(['success' => false, 'message' => '账号已被冻结或封禁'], 403);
            return;
        }

        $this->recordLoginAttempt($ip, $username, true);

        $this->regenerateSessionIdSafely();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if (!empty($_POST['remember'])) {
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443');

            $ttlDays = 30;
            $cookieExpiresTs = time() + ($ttlDays * 86400);
            $expiresDateTime = date('Y-m-d H:i:s', $cookieExpiresTs);

            $selector = bin2hex(random_bytes(12));   // 12 bytes => 24 hex chars
            $validator = bin2hex(random_bytes(32)); // 32 bytes => 64 hex chars
            $hash = hash('sha256', $validator);

            try {
                $this->users->storeRememberToken((int)$user['id'], $selector, $hash, $expiresDateTime);
                setcookie('remember_me', $selector . ':' . $validator, [
                    'expires' => $cookieExpiresTs,
                    'path' => '/',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            } catch (\Throwable $e) {
                // Avoid breaking login on token persistence failures.
            }
        }

        try {
            $this->audit->logAction(
                (int)$user['id'],
                'LOGIN',
                $ip,
                ['username' => $user['username'], 'success' => true]
            );
        } catch (\Throwable $e) {
            // 忽略审计失败，避免影响登录
        }

        if ($mcUuid === '' && $mcUsername !== '') {
            $mcUuid = MinecraftUuid::getOfflineUuid($mcUsername);
            $mcName = $mcUsername;
        }

        if ($mcUuid !== '') {
            try {
                $this->users->bindMinecraftAccount((int)$user['id'], $mcUuid, $mcName);
                // TODO: 随后可通过发起 HTTP 请求通知 Minecraft 服务端解除玩家冻结
            } catch (\Throwable $e) {
                // 忽略绑定失败，避免影响登录
            }
        }

        $this->json(['success' => true, 'message' => '登录成功']);
    }

    public function register(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $ip = $this->getClientIp();
        $isWhite = $this->isIpWhitelisted($ip);
        $block = !$isWhite ? $this->findIpBlacklistMatch($ip) : null;
        if ($block !== null) {
            try {
                $this->audit->logAction(null, 'IP_BANNED_BLOCKED', $ip, [
                    'route' => '/auth/register',
                    'reason' => $block['reason'],
                    'matched_rule' => $block['matched_rule'],
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '访问被拒绝'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $this->checkRateLimit('auth_register|' . $ip, 5, 60);
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $passwordRaw = (string)($_POST['password'] ?? '');
        $turnstileResponse = trim((string)($_POST['cf-turnstile-response'] ?? ''));
        $mcUuid = trim((string)($_POST['mc_uuid'] ?? '')); // legacy support
        $mcName = trim((string)($_POST['mc_name'] ?? '')); // legacy support
        $mcUsername = trim((string)($_POST['mc_username'] ?? ''));
        $pendingOAuth = $_SESSION['oauth_pending_user'] ?? null;
        $isMuaPending = is_array($pendingOAuth)
            && (($pendingOAuth['provider'] ?? '') === 'mua')
            && !empty($pendingOAuth['sub'])
            && !empty($pendingOAuth['email']);

        if ($username === '' || $email === '' || $passwordRaw === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        // === 新增：Session 轻量级冷却限流（无数据库）===
        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $cooldown = defined('AUTH_ACTION_COOLDOWN') ? (int)AUTH_ACTION_COOLDOWN : 60;
            $last = isset($_SESSION['last_auth_action']) ? (int)$_SESSION['last_auth_action'] : 0;
            if ($last > 0 && (time() - $last) < $cooldown) {
                $this->json(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
                return;
            }
        }

        if ($turnstileResponse === '' || !$this->verifyTurnstile($turnstileResponse, $ip)) {
            $this->json(['success' => false, 'message' => '人机验证失败，请重试'], 403);
            return;
        }

        if (!$isMuaPending && !$this->isEmailAllowed($email)) {
            $this->json(['success' => false, 'message' => '暂不支持该邮箱域名'], 400);
            return;
        }

        if ($isMuaPending) {
            $pendingEmail = (string)$pendingOAuth['email'];
            if (!hash_equals(strtolower($pendingEmail), strtolower($email))) {
                $this->json(['success' => false, 'message' => 'MUA 授权邮箱与注册邮箱不一致'], 400);
                return;
            }
        }

        if ($this->isRegistrationLimited($ip, $isWhite)) {
            $lim = max(0, (int)$this->getSiteSetting('register_ip_limit', '2'));
            $this->json(['success' => false, 'message' => '同一 IP 24 小时内仅允许注册 ' . $lim . ' 个账号'], 429);
            return;
        }

        if ($this->users->findByUsername($username) || $this->users->findByEmail($email)) {
            $this->json(['success' => false, 'message' => '用户名或邮箱已存在'], 400);
            return;
        }

        // 使用 AuthMe 原生 SHA256 算法生成哈希
        $hash = $this->generateAuthMeHash($passwordRaw);

        $userId = $this->users->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash,
            'role' => 'player',
            'status' => 'active',
            'email_verified' => $isMuaPending ? 1 : 0,
            'mua_sub' => $isMuaPending ? (string)$pendingOAuth['sub'] : null,
        ]);

        $this->recordRegistration($ip);
        try {
            $this->audit->logAction(
                $userId,
                'REGISTER',
                $ip,
                ['username' => $username, 'email' => $email, 'success' => true]
            );
        } catch (\Throwable $e) {
            // 忽略审计失败，避免影响注册
        }

        if ($mcUuid === '' && $mcUsername !== '') {
            $mcUuid = MinecraftUuid::getOfflineUuid($mcUsername);
            $mcName = $mcUsername;
        }

        if ($mcUuid !== '') {
            try {
                $this->users->bindMinecraftAccount((int)$userId, $mcUuid, $mcName);
                // TODO: 随后可通过发起 HTTP 请求通知 Minecraft 服务端解除玩家冻结
            } catch (\Throwable $e) {
                // 忽略绑定失败，避免影响注册
            }
        }

        if ($isMuaPending) {
            unset($_SESSION['oauth_pending_user']);
            $_SESSION['last_auth_action'] = time();
            $this->json(['success' => true, 'message' => '注册成功，已完成 MUA 账号绑定']);
            return;
        }

        try {
            $this->sendVerificationEmail($userId, $email);
            // === 新增：注册成功（发送验证邮件后）写入冷却时间 ===
            $_SESSION['last_auth_action'] = time();
            $this->json(['success' => true, 'message' => '注册成功，请查收邮箱完成验证']);
        } catch (\Exception $e) {
            // 仅在开发模式显示真实原因，生产环境模糊提示
            if (APP_ENV === 'development') {
                $this->json(['success' => false, 'message' => '邮件发送失败: ' . $e->getMessage()], 500);
            } else {
                $this->json(['success' => false, 'message' => '系统邮件服务异常，请联系管理员'], 500);
            }
        }
    }

    public function logout(): void
    {
        if (!empty($_COOKIE['remember_me'])) {
            $cookieVal = (string)$_COOKIE['remember_me'];
            $parts = explode(':', $cookieVal, 2);
            $selector = trim((string)($parts[0] ?? ''));

            if ($selector !== '') {
                try {
                    $this->users->deleteRememberToken($selector);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        $this->clearRememberMeCookie();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool)($params['secure'] ?? false),
                    'httponly' => (bool)($params['httponly'] ?? true),
                    'samesite' => (string)($params['samesite'] ?? 'Lax'),
                ]);
            }

            session_destroy();
        }

        header('Location: /');
        exit;
    }

    public function verify(): string
    {
        $token = (string)($_GET['token'] ?? '');
        $token = trim($token);
        $message = '验证链接无效或已失效';

        if ($token !== '') {
            $db = \Core\Database::connection();
            $stmt = $db->prepare('SELECT * FROM email_verifications WHERE token = :token AND used = 0 LIMIT 1');
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch();

            if ($row) {
                // 标记已使用
                $upd = $db->prepare('UPDATE email_verifications SET used = 1, used_at = NOW() WHERE id = :id');
                $upd->execute([':id' => $row['id']]);

                // 更新用户邮箱验证状态
                $this->users->markEmailVerified((int)$row['user_id']);
                $message = '邮箱验证成功，现在可以正常登录服务器了。';
            }
        }

        return $this->render('auth/verify_result', [
            'title' => '邮箱验证',
            'message' => $message,
        ]);
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

    private function isEmailAllowed(string $email): bool
    {
        $domain = strtolower((string)substr(strrchr($email, '@'), 1));
        if ($domain === '') {
            return false;
        }

        // 允许的主流邮箱白名单，可在部署时扩展
        $allowed = [
            'qq.com',
            '163.com',
            '126.com',
            'yeah.net',
            'gmail.com',
            'outlook.com',
            'hotmail.com',
            'live.com',
            'proton.me',
        ];

        // 预留：可在此加入常见临时邮箱黑名单逻辑

        return in_array($domain, $allowed, true);
    }

    private function isRegistrationLimited(string $ip, bool $isWhitelisted): bool
    {
        if ($isWhitelisted) {
            return false;
        }
        if (APP_ENV === 'development') {
            return false;
        }

        $limit = (int)$this->getSiteSetting('register_ip_limit', '2');
        if ($limit <= 0) {
            return false;
        }

        $db = \Core\Database::connection();
        $stmt = $db->prepare('
            SELECT COUNT(*) AS c 
            FROM registration_limits 
            WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ');
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch();

        return $row && (int)$row['c'] >= $limit;
    }

    private function recordRegistration(string $ip): void
    {
        $db = \Core\Database::connection();
        $stmt = $db->prepare('INSERT INTO registration_limits (ip_address, created_at) VALUES (:ip, NOW())');
        $stmt->execute([':ip' => $ip]);
    }

    private function isLoginLocked(string $ip, string $username): bool
    {
        $db = \Core\Database::connection();
        $stmt = $db->prepare('
            SELECT 
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) AS fail_count,
                MAX(created_at) AS last_fail_time
            FROM login_attempts
            WHERE (ip_address = :ip OR username = :username)
              AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ');
        $stmt->execute([':ip' => $ip, ':username' => $username]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return (int)$row['fail_count'] >= 5;
    }

    private function recordLoginAttempt(string $ip, string $username, bool $success): void
    {
        $db = \Core\Database::connection();
        $stmt = $db->prepare('
            INSERT INTO login_attempts (ip_address, username, success, created_at)
            VALUES (:ip, :username, :success, NOW())
        ');
        $stmt->execute([
            ':ip' => $ip,
            ':username' => $username,
            ':success' => $success ? 1 : 0,
        ]);
    }

    private function sendVerificationEmail(int $userId, string $email): void
    {
        $db = \Core\Database::connection();
        $token = bin2hex(random_bytes(16));

        $stmt = $db->prepare('
            INSERT INTO email_verifications (user_id, token, created_at, used)
            VALUES (:user_id, :token, NOW(), 0)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
        ]);

        $verifyUrl = sprintf('%s://%s/auth/verify?token=%s',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http',
            $_SERVER['HTTP_HOST'] ?? 'localhost',
            urlencode($token)
        );
        
        $subject = '繁星World 账号邮箱验证';
        $uStmt = $db->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
        $uStmt->execute([':id' => $userId]);
        $uRow = $uStmt->fetch();
        $username = '';
        if ($uRow && isset($uRow['username'])) {
            $username = trim((string)$uRow['username']);
        }
        if ($username === '') {
            $username = '玩家';
        }

        // TODO: 请将此处的 LOGO_URL 替换为您真实的图片链接
        $template = '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 10px;background:#0f172a;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111827;border-radius:14px;overflow:hidden;border:1px solid #1f2937;">
<tr><td align="center" style="padding:30px 20px;background:#020617;">
<img src="LOGO_URL" width="80" style="display:block;margin-bottom:10px;">
<h2 style="margin:0;font-weight:600;color:#38bdf8;">SERVER_NAME</h2>
</td></tr>
<tr><td style="padding:40px 35px;text-align:center;">
<h1 style="margin-top:0;font-size:24px;color:#ffffff;">欢迎加入服务器 🎮</h1>
<p style="color:#cbd5f5;font-size:15px;line-height:1.6;">你好 <b>USERNAME</b>，<br>感谢你注册我们的 Minecraft 服务器网站。<br>请点击下面按钮验证你的邮箱。</p>
<table cellpadding="0" cellspacing="0" align="center" style="margin-top:30px;"><tr><td align="center" bgcolor="#2563eb" style="border-radius:8px;">
<a href="VERIFY_LINK" style="display:inline-block;padding:14px 30px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:16px;">验证邮箱</a>
</td></tr></table>
<p style="margin-top:30px;color:#94a3b8;font-size:13px;">如果按钮无法点击，请复制下面链接到浏览器：</p>
<p style="word-break:break-all;color:#38bdf8;font-size:13px;">VERIFY_LINK</p>
</td></tr>
<tr><td style="padding:25px;text-align:center;background:#020617;color:#64748b;font-size:12px;">© 2026 SERVER_NAME<br>Minecraft Server Network</td></tr>
</table></td></tr></table></body></html>';

        $body = str_replace(
            ['LOGO_URL', 'SERVER_NAME', 'USERNAME', 'VERIFY_LINK'],
            ['https://www.stellarvan.cn/images/logo.png', '繁星World', htmlspecialchars($username, ENT_QUOTES, 'UTF-8'), htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8')],
            $template
        );

        $mail = new PHPMailer(true);
        
        // 仅开发模式打印 SMTP 握手底层日志
        if (APP_ENV === 'development') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP 调试日志: $str");
            };
        }

        try {
            // 服务器配置
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            // 自动根据端口判断加密方式（465使用SSL，587使用TLS）
            $mail->SMTPSecure = (SMTP_PORT === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // 发件人与收件人
            $mail->setFrom(SMTP_FROM_EMAIL, '繁星World');
            $mail->addAddress($email);

            // 邮件内容
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
        } catch (Exception $e) {
            throw new \Exception($mail->ErrorInfo);
        }
    }

    private function verifyTurnstile(string $response, string $ip): bool
    {
        $secret = defined('TURNSTILE_SECRET_KEY')
            ? (string)TURNSTILE_SECRET_KEY
            : (string)(getenv('TURNSTILE_SECRET_KEY') ?: '');
        if ($secret === '') {
            return APP_ENV === 'development';
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $response,
            'remoteip' => $ip,
        ]);

        // 优先使用 curl（更稳定）；否则退化到 file_get_contents
        if (function_exists('curl_init')) {
            $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 5,
                ],
            ]);
            $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        }

        if (!is_string($resp) || $resp === '') {
            return false;
        }
        $data = json_decode($resp, true);
        return is_array($data) && !empty($data['success']);
    }

    private function muaRequestToken(string $code): array
    {
        $payload = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => (string)MUA_CLIENT_ID,
            'client_secret' => (string)MUA_CLIENT_SECRET,
            'redirect_uri' => (string)MUA_REDIRECT_URI,
            'code' => $code,
        ]);

        $ch = curl_init('https://skin.mualliance.ltd/api/union/oauth2/token');
        if ($ch === false) {
            throw new \RuntimeException('cURL 初始化失败');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('cURL Error: ' . curl_error($ch));
        }
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($resp) || $resp === '') {
            throw new \RuntimeException('Token 请求失败: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('MUA token endpoint http error: ' . $httpCode);
            throw new \RuntimeException('Token 请求返回状态码异常: ' . $httpCode);
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Token 响应解析失败');
        }

        return $data;
    }

    private function muaRequestUser(string $accessToken): array
    {
        $ch = curl_init('https://skin.mualliance.ltd/api/union/oauth2/user');
        if ($ch === false) {
            throw new \RuntimeException('cURL 初始化失败');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('cURL Error: ' . curl_error($ch));
        }
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($resp) || $resp === '') {
            throw new \RuntimeException('用户信息请求失败: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('MUA user endpoint http error: ' . $httpCode);
            throw new \RuntimeException('用户信息请求返回状态码异常: ' . $httpCode);
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new \RuntimeException('用户信息响应解析失败');
        }

        return $data;
    }

    private function signInUser(array $user): void
    {
        if (($user['status'] ?? 'active') !== 'active') {
            throw new \RuntimeException('账号状态异常，无法登录');
        }

        $this->regenerateSessionIdSafely();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role'] = (string)$user['role'];
    }

    private function clearRememberMeCookie(): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        setcookie('remember_me', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function syncMuaSkinToGameByUser(int $userId): void
    {
        if ($userId <= 0 || !defined('RCON_PASSWORD') || trim((string)RCON_PASSWORD) === '') {
            return;
        }

        $profile = $this->users->getProfile($userId);
        if (!is_array($profile)) {
            return;
        }

        $muaSub = trim((string)($profile['mua_sub'] ?? ''));
        if ($muaSub === '') {
            return;
        }

        $playerName = trim((string)($profile['mc_username'] ?? ''));
        if ($playerName === '') {
            $playerName = trim((string)($profile['username'] ?? ''));
        }
        if ($playerName === '') {
            return;
        }

        $skinUrl = (new MUAFetcher())->getSkinDirectUrl($playerName, $muaSub);
        if (!is_string($skinUrl) || $skinUrl === '') {
            return;
        }

        $skinAlias = 'mua_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $muaSub);
        if ($skinAlias === 'mua_') {
            return;
        }

        $rcon = new RconClient((string)RCON_HOST, (int)RCON_PORT, (string)RCON_PASSWORD, (float)RCON_TIMEOUT);
        $rcon->sendCommand('sr createcustom ' . $skinAlias . ' ' . $skinUrl);
        $rcon->sendCommand('skin set ' . $skinAlias . ' ' . $muaSub);
    }

    /**
     * @return array{reason: string, matched_rule: string}|null
     */
    private function findIpBlacklistMatch(string $clientIp): ?array
    {
        $normalized = filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        if ($normalized === false) {
            return null;
        }

        try {
            $db = \Core\Database::connection();
            $stmt = $db->query('SELECT ip_cidr, reason FROM ip_blacklist');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($rows ?: [] as $row) {
            $rule = trim((string)($row['ip_cidr'] ?? ''));
            if ($rule === '') {
                continue;
            }
            if ($this->clientIpMatchesRule($normalized, $rule)) {
                return [
                    'matched_rule' => $rule,
                    'reason' => trim((string)($row['reason'] ?? '')),
                ];
            }
        }

        return null;
    }

    private function clientIpMatchesRule(string $clientIp, string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') {
            return false;
        }

        if (strpos($rule, '/') !== false) {
            $parts = explode('/', $rule, 2);
            $addr = trim($parts[0]);
            $prefStr = trim($parts[1]);
            if ($prefStr === '' || !ctype_digit($prefStr)) {
                return false;
            }
            $prefix = (int)$prefStr;

            if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                if ($prefix < 0 || $prefix > 32) {
                    return false;
                }
                if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    return false;
                }
                return $this->ipv4CidrContains($clientIp, $addr, $prefix);
            }

            if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                if ($prefix < 0 || $prefix > 128) {
                    return false;
                }
                if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                    return false;
                }
                return $this->ipv6CidrContains($clientIp, $addr, $prefix);
            }

            return false;
        }

        $ruleIp = filter_var($rule, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        if ($ruleIp === false) {
            return false;
        }

        $a = @inet_pton($ruleIp);
        $b = @inet_pton($clientIp);
        if ($a !== false && $b !== false && strlen($a) === strlen($b)) {
            return $a === $b;
        }

        return strcasecmp($ruleIp, $clientIp) === 0;
    }

    private function ipv4ToUint32(string $ipv4): ?int
    {
        if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }
        $l = ip2long($ipv4);
        if ($l === false) {
            return null;
        }

        return (int)($l & 0xFFFFFFFF);
    }

    private function ipv4CidrContains(string $clientIp, string $networkIp, int $prefix): bool
    {
        $ip = $this->ipv4ToUint32($clientIp);
        $net = $this->ipv4ToUint32($networkIp);
        if ($ip === null || $net === null) {
            return false;
        }

        if ($prefix <= 0) {
            return true;
        }
        if ($prefix >= 32) {
            return $ip === $net;
        }

        $hostBits = (1 << (32 - $prefix)) - 1;
        $mask = (int)((0xFFFFFFFF ^ $hostBits) & 0xFFFFFFFF);

        return (($ip & $mask) === ($net & $mask));
    }

    private function ipv6CidrContains(string $clientIp, string $networkIp, int $prefix): bool
    {
        $a = @inet_pton($clientIp);
        $b = @inet_pton($networkIp);
        if ($a === false || $b === false || strlen($a) !== 16 || strlen($b) !== 16) {
            return false;
        }

        if ($prefix <= 0) {
            return true;
        }
        if ($prefix >= 128) {
            return $a === $b;
        }

        $fullBytes = intdiv($prefix, 8);
        $remBits = $prefix % 8;

        if ($fullBytes > 0 && strncmp($a, $b, $fullBytes) !== 0) {
            return false;
        }

        if ($remBits === 0) {
            return true;
        }

        $mask = ((1 << $remBits) - 1) << (8 - $remBits);

        return (ord($a[$fullBytes]) & $mask) === (ord($b[$fullBytes]) & $mask);
    }

    private function getSiteSetting(string $key, string $default = ''): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $db = \Core\Database::connection();
                $stmt = $db->query('SELECT setting_key, setting_value FROM site_settings');
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $k = (string)($row['setting_key'] ?? '');
                    if ($k !== '') {
                        $cache[$k] = (string)($row['setting_value'] ?? '');
                    }
                }
            } catch (\Throwable $e) {
                // 表不存在或查询失败时使用默认值
            }
        }

        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }

    private function whitelistSkipsRateLimits(bool $isWhitelistedIp): bool
    {
        return $isWhitelistedIp && $this->getSiteSetting('whitelist_ignores_rate_limit', '0') === '1';
    }

    private function isIpWhitelisted(string $clientIp): bool
    {
        $normalized = filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        if ($normalized === false) {
            return false;
        }

        try {
            $db = \Core\Database::connection();
            $stmt = $db->query('SELECT ip_cidr FROM ip_whitelist');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }

        foreach ($rows ?: [] as $row) {
            $rule = trim((string)($row['ip_cidr'] ?? ''));
            if ($rule !== '' && $this->clientIpMatchesRule($normalized, $rule)) {
                return true;
            }
        }

        return false;
    }
}
