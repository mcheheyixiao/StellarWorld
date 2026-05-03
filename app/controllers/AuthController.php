<?php
declare(strict_types=1);

namespace Controller;

use Core\AuthMePassword;
use Core\CaptchaService;
use Core\Controller;
use Core\EmailCodeService;
use Core\EmailDomainWhitelist;
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
    private CaptchaService $captchaService;
    private EmailCodeService $emailCodeService;

    public function __construct()
    {
        parent::__construct();
        $this->users = new User();
        $this->audit = new AuditModel();
        $this->captchaService = new CaptchaService();
        $this->emailCodeService = new EmailCodeService();
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
        $pendingOAuth = $this->getFreshPendingOAuth();
        $oauthPendingEmail = is_array($pendingOAuth) ? trim((string)($pendingOAuth['email'] ?? '')) : '';
        $oauthPendingProvider = is_array($pendingOAuth) ? trim((string)($pendingOAuth['provider'] ?? '')) : '';
        $oauthPendingMcUsername = is_array($pendingOAuth) ? trim((string)($pendingOAuth['mc_username'] ?? '')) : '';
        return $this->render('auth/register', [
            'title' => '玩家注册',
            'oauthPendingEmail' => $oauthPendingEmail,
            'oauthPendingProvider' => $oauthPendingProvider,
            'oauthPendingMcUsername' => $oauthPendingMcUsername,
            'registerRequiresEmailCode' => $oauthPendingEmail === '',
            'registerEmailCodeCooldownSeconds' => max(30, (int)$this->getSiteSetting('email_code_send_cooldown_seconds', (string)DEFAULT_EMAIL_CODE_SEND_COOLDOWN_SECONDS)),
        ]);
    }

    public function sendEmailCode(): void
    {
        $this->validateCsrfToken();
        header('Content-Type: application/json; charset=utf-8');

        $ip = $this->getClientIp();
        $isWhite = $this->isIpWhitelisted($ip);
        $block = !$isWhite ? $this->findIpBlacklistMatch($ip) : null;
        if ($block !== null) {
            try {
                $this->audit->logAction(null, 'IP_BANNED_BLOCKED', $ip, [
                    'route' => '/auth/email-code/send',
                    'reason' => $block['reason'],
                    'matched_rule' => $block['matched_rule'],
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '访问被拒绝'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $this->checkRateLimit('auth_email_code|' . $ip, 10, 3600);
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $purpose = trim((string)($_POST['purpose'] ?? 'register'));
        $captchaAnswer = trim((string)($_POST['captcha_answer'] ?? ''));
        $maskedEmail = EmailCodeService::maskEmail($email);

        if ($email === '' || $captchaAnswer === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        if ($purpose !== 'register') {
            $this->json(['success' => false, 'message' => '不支持的验证码用途'], 400);
            return;
        }

        if (!$this->captchaService->verify($captchaAnswer, 'email_code')) {
            try {
                $this->audit->logAction(null, 'EMAIL_CODE_CAPTCHA_FAILED', $ip, [
                    'email_mask' => $maskedEmail,
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                    'purpose' => $purpose,
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '图形验证码错误或已失效'], 403);
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->json(['success' => false, 'message' => '邮箱格式不正确'], 400);
            return;
        }

        if (!$this->isEmailAllowed($email)) {
            $this->json(['success' => false, 'message' => '暂不支持该邮箱域名'], 400);
            return;
        }

        if ($this->users->findByEmail($email)) {
            $this->json(['success' => false, 'message' => '该邮箱已被注册'], 400);
            return;
        }

        try {
            $this->emailCodeService->sendCode(
                $email,
                'register',
                $ip,
                (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
            $this->audit->logAction(null, 'EMAIL_CODE_SENT', $ip, [
                'email_mask' => $maskedEmail,
                'email_hash' => EmailCodeService::hashIdentifier($email),
                'purpose' => $purpose,
            ]);
            $this->json(['success' => true, 'message' => '验证码已发送，请查收邮箱'], 200);
        } catch (\RuntimeException $e) {
            try {
                $this->audit->logAction(null, 'EMAIL_CODE_SEND_BLOCKED', $ip, [
                    'email_mask' => $maskedEmail,
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                    'purpose' => $purpose,
                    'reason' => $e->getMessage(),
                ]);
            } catch (\Throwable $ignored) {
            }

            $message = $e->getMessage();
            $status = str_contains($message, '频繁') || str_contains($message, '上限') ? 429 : 400;
            $this->json(['success' => false, 'message' => $message], $status);
        } catch (\Throwable $e) {
            try {
                $this->audit->logAction(null, 'EMAIL_CODE_SEND_ERROR', $ip, [
                    'email_mask' => $maskedEmail,
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                    'purpose' => $purpose,
                ]);
            } catch (\Throwable $ignored) {
            }

            $this->json(['success' => false, 'message' => '系统邮件服务异常，请稍后重试'], 500);
        }
    }

    public function captcha(): void
    {
        $purpose = 'login';

        try {
            $purpose = $this->resolveCaptchaPurpose((string)($_GET['purpose'] ?? 'login'));
            $issued = $this->captchaService->issue($purpose, $this->getClientIp());

            http_response_code(200);
            header('Content-Type: image/svg+xml; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $issued['svg'];
            exit;
        } catch (\Throwable $e) {
            http_response_code(429);
            header('Content-Type: image/svg+xml; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $this->buildCaptchaErrorSvg('captcha refresh limited');
            exit;
        }
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
            $isDevelopment = strtolower((string)(defined('APP_ENV') ? APP_ENV : 'production')) === 'development';
            $this->json([
                'success' => false,
                'message' => $isDevelopment
                    ? 'OAuth回调处理失败: ' . $e->getMessage()
                    : 'OAuth 回调处理失败，请稍后重试或联系管理员',
            ], 500);
        }
    }

    public function microsoftRedirect(): void
    {
        $clientId = trim((string)MICROSOFT_CLIENT_ID);
        $clientSecret = trim((string)MICROSOFT_CLIENT_SECRET);
        $redirectUri = trim((string)MICROSOFT_REDIRECT_URI);
        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            $isDevelopment = $this->isDevelopmentEnvironment();
            $this->json([
                'success' => false,
                'message' => $isDevelopment
                    ? 'Microsoft 正版登录配置不完整，请检查 MICROSOFT_CLIENT_ID / MICROSOFT_CLIENT_SECRET / MICROSOFT_REDIRECT_URI'
                    : 'Microsoft 正版登录暂未正确配置，请稍后再试',
            ], 500);
            return;
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['microsoft_oauth_state'] = $state;
        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => (string)MICROSOFT_OAUTH_SCOPE,
            'state' => $state,
        ]);

        header('Location: ' . (string)MICROSOFT_OAUTH_AUTHORIZE_URL . '?' . $query);
        exit;
    }

    public function microsoftCallback(): void
    {
        try {
            $state = trim((string)($_GET['state'] ?? ''));
            $sessionState = (string)($_SESSION['microsoft_oauth_state'] ?? '');
            unset($_SESSION['microsoft_oauth_state']);

            if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
                throw new \RuntimeException('登录状态校验失败，请重新尝试');
            }

            $code = trim((string)($_GET['code'] ?? ''));
            if ($code === '') {
                throw new \RuntimeException('Microsoft 授权失败，请重新尝试');
            }

            $tokenData = $this->microsoftRequestToken($code);
            $accessToken = trim((string)($tokenData['access_token'] ?? ''));
            if ($accessToken === '') {
                throw new \RuntimeException('Microsoft 授权失败，请重新尝试');
            }

            $xblData = $this->xboxLiveAuthenticate($accessToken);
            $xblToken = trim((string)($xblData['Token'] ?? ''));
            if ($xblToken === '') {
                throw new \RuntimeException('Xbox Live 认证失败，请确认该账号可正常使用 Xbox 服务');
            }

            $xstsData = $this->xstsAuthorize($xblToken);
            $xstsToken = trim((string)($xstsData['Token'] ?? ''));
            $uhs = trim((string)($xstsData['DisplayClaims']['xui'][0]['uhs'] ?? ''));
            if ($xstsToken === '' || $uhs === '') {
                throw new \RuntimeException('XSTS 授权失败，请稍后重试');
            }

            $minecraftLogin = $this->minecraftLoginWithXbox($uhs, $xstsToken);
            $minecraftAccessToken = trim((string)($minecraftLogin['access_token'] ?? ''));
            if ($minecraftAccessToken === '') {
                throw new \RuntimeException('Minecraft 服务登录失败，请稍后重试');
            }

            $minecraftProfile = $this->minecraftRequestProfile($minecraftAccessToken);
            $minecraftUuid = trim((string)($minecraftProfile['id'] ?? ''));
            $minecraftName = trim((string)($minecraftProfile['name'] ?? ''));
            if ($minecraftUuid === '' || $minecraftName === '') {
                throw new \RuntimeException('该 Microsoft 账号未拥有 Minecraft Java Edition，或暂时无法读取正版档案。');
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            if ($currentUserId > 0) {
                $currentUser = $this->users->getProfile($currentUserId);
                if (!$currentUser || (($currentUser['status'] ?? 'active') !== 'active')) {
                    throw new \RuntimeException('当前账号状态异常，无法绑定正版账号');
                }

                $boundByUuid = $this->users->findByMinecraftUuid($minecraftUuid);
                if ($boundByUuid && (int)$boundByUuid['id'] !== $currentUserId) {
                    throw new \RuntimeException('该 Minecraft 正版账号已绑定其他网站账号，无法重复绑定');
                }

                $this->users->bindMinecraftAccount($currentUserId, $minecraftUuid, $minecraftName);
                header('Location: /profile');
                exit;
            }

            $boundByUuid = $this->users->findByMinecraftUuid($minecraftUuid);
            if ($boundByUuid) {
                $this->signInUser($boundByUuid);
                header('Location: /');
                exit;
            }

            $_SESSION['oauth_pending_user'] = [
                'provider' => 'microsoft_minecraft',
                'sub' => $minecraftUuid,
                'mc_uuid' => $minecraftUuid,
                'mc_username' => $minecraftName,
                'nickname' => $minecraftName,
                'issued_at' => time(),
            ];
            header('Location: /auth/register');
            exit;
        } catch (\Throwable $e) {
            error_log('Microsoft Minecraft OAuth callback error: ' . $e->getMessage());
            $isDevelopment = $this->isDevelopmentEnvironment();
            $this->json([
                'success' => false,
                'message' => $isDevelopment
                    ? $e->getMessage()
                    : $this->resolveMicrosoftMinecraftProductionErrorMessage($e->getMessage()),
            ], 400);
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

        $captchaAnswer = trim((string)($_POST['captcha_answer'] ?? ''));
        if ($captchaAnswer === '' || !$this->captchaService->verify($captchaAnswer, 'forgot_password')) {
            try {
                $this->audit->logAction(null, 'PASSWORD_RESET_CAPTCHA_FAILED', $ip, [
                    'email_mask' => EmailCodeService::maskEmail($email),
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '人机验证失败，请重试'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $cooldown = defined('AUTH_ACTION_COOLDOWN') ? (int)AUTH_ACTION_COOLDOWN : 60;
            $last = isset($_SESSION['last_auth_action']) ? (int)$_SESSION['last_auth_action'] : 0;
            if ($last > 0 && (time() - $last) < $cooldown) {
                $this->json(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
                return;
            }
        }

        $genericResetMessage = '如果该邮箱已注册，我们会发送重置链接，请查收邮箱。';
        $user = $this->users->findByEmail($email);
        if (!$user) {
            try {
                $this->audit->logAction(null, 'PASSWORD_RESET_EMAIL_NOT_FOUND', $ip, [
                    'email_mask' => EmailCodeService::maskEmail($email),
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                ]);
            } catch (\Throwable $e) {
            }
            $_SESSION['last_auth_action'] = time();
            $this->json(['success' => true, 'message' => $genericResetMessage], 200);
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
            try {
                $this->audit->logAction((int)($user['id'] ?? 0) ?: null, 'PASSWORD_RESET_LINK_SENT', $ip, [
                    'email_mask' => EmailCodeService::maskEmail($email),
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                ]);
            } catch (\Throwable $e) {
            }

            // === 新增：找回密码成功后写入冷却时间 ===
            $_SESSION['last_auth_action'] = time();
            $this->json(['success' => true, 'message' => $genericResetMessage]);
        } catch (\Throwable $e) {
            try {
                $this->audit->logAction((int)($user['id'] ?? 0) ?: null, 'PASSWORD_RESET_LINK_ERROR', $ip, [
                    'email_mask' => EmailCodeService::maskEmail($email),
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                ]);
            } catch (\Throwable $ignored) {
            }
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
        $hash = AuthMePassword::hash($passwordRaw);

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
        try {
            $user = $this->users->findByEmail($email);
            $this->audit->logAction(is_array($user) && isset($user['id']) ? (int)$user['id'] : null, 'PASSWORD_RESET_COMPLETED', $ip, [
                'email_mask' => EmailCodeService::maskEmail($email),
                'email_hash' => EmailCodeService::hashIdentifier($email),
            ]);
        } catch (\Throwable $e) {
        }

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
        $pendingOAuth = $this->getFreshPendingOAuth();
        $pendingOAuthProvider = is_array($pendingOAuth) ? (string)($pendingOAuth['provider'] ?? '') : '';
        $pendingOAuthSub = is_array($pendingOAuth) ? trim((string)($pendingOAuth['sub'] ?? '')) : '';
        $pendingOAuthEmail = is_array($pendingOAuth) ? trim((string)($pendingOAuth['email'] ?? '')) : '';
        $pendingOAuthMcUuid = is_array($pendingOAuth) ? trim((string)($pendingOAuth['mc_uuid'] ?? $pendingOAuthSub)) : '';
        $pendingOAuthMcUsername = is_array($pendingOAuth) ? trim((string)($pendingOAuth['mc_username'] ?? $pendingOAuth['nickname'] ?? '')) : '';
        $isFreshPendingOAuth = is_array($pendingOAuth)
            && in_array($pendingOAuthProvider, ['mua', 'microsoft_minecraft'], true)
            && $pendingOAuthSub !== '';

        if ($username === '' || $passwordRaw === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        $captchaAnswer = trim((string)($_POST['captcha_answer'] ?? ''));
        if ($captchaAnswer === '' || !$this->captchaService->verify($captchaAnswer, 'login')) {
            try {
                $this->audit->logAction(null, 'LOGIN_CAPTCHA_FAILED', $ip, [
                    'username' => $username,
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '人机验证失败，请重试'], 403);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite) && $this->isLoginLocked($ip, $username)) {
            $this->json(['success' => false, 'message' => '尝试次数过多，请稍后再试'], 429);
            return;
        }

        $user = $this->users->findByUsername($username);
        if (!$user || !AuthMePassword::verify($passwordRaw, (string)$user['password_hash'])) {
            $this->recordLoginAttempt($ip, $username, false);
            try {
                $this->audit->logAction(null, 'LOGIN_FAILED', $ip, [
                    'username' => $username,
                ]);
            } catch (\Throwable $e) {
            }
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

        $loginSuccessMessage = '登录成功';
        if ($isFreshPendingOAuth) {
            $canBindToCurrentUser = $pendingOAuthEmail === ''
                || hash_equals(strtolower($pendingOAuthEmail), strtolower((string)($user['email'] ?? '')));

            if ($canBindToCurrentUser) {
                try {
                    if ($pendingOAuthProvider === 'mua') {
                        $this->users->bindMuaSub((int)$user['id'], $pendingOAuthSub);
                        $this->syncMuaSkinToGameByUser((int)$user['id']);
                    } elseif ($pendingOAuthProvider === 'microsoft_minecraft' && $pendingOAuthMcUuid !== '') {
                        $boundByUuid = $this->users->findByMinecraftUuid($pendingOAuthMcUuid);
                        if ($boundByUuid && (int)$boundByUuid['id'] !== (int)$user['id']) {
                            $loginSuccessMessage = '登录成功，但该 Minecraft 正版账号已绑定其他网站账号';
                        } else {
                            $this->users->bindMinecraftAccount(
                                (int)$user['id'],
                                $pendingOAuthMcUuid,
                                $pendingOAuthMcUsername !== '' ? $pendingOAuthMcUsername : $pendingOAuthMcUuid
                            );
                            $loginSuccessMessage = '登录成功，已绑定 Microsoft 正版账号';
                        }
                    }
                } catch (\Throwable $e) {
                    // Avoid breaking password login if OAuth binding fails.
                }
            }

            unset($_SESSION['oauth_pending_user']);
        }

        $this->json(['success' => true, 'message' => $loginSuccessMessage]);
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
        $captchaAnswer = trim((string)($_POST['captcha_answer'] ?? ''));
        $emailCode = trim((string)($_POST['email_code'] ?? ''));
        $mcUuid = trim((string)($_POST['mc_uuid'] ?? '')); // legacy support
        $mcName = trim((string)($_POST['mc_name'] ?? '')); // legacy support
        $mcUsername = trim((string)($_POST['mc_username'] ?? ''));
        $pendingOAuth = $this->getFreshPendingOAuth();
        $pendingOAuthProvider = is_array($pendingOAuth) ? (string)($pendingOAuth['provider'] ?? '') : '';
        $pendingOAuthSub = is_array($pendingOAuth) ? trim((string)($pendingOAuth['sub'] ?? '')) : '';
        $pendingOAuthEmail = is_array($pendingOAuth) ? trim((string)($pendingOAuth['email'] ?? '')) : '';
        $pendingOAuthNickname = is_array($pendingOAuth) ? trim((string)($pendingOAuth['nickname'] ?? '')) : '';
        $pendingOAuthMcUuid = is_array($pendingOAuth) ? trim((string)($pendingOAuth['mc_uuid'] ?? $pendingOAuthSub)) : '';
        $pendingOAuthMcUsername = is_array($pendingOAuth) ? trim((string)($pendingOAuth['mc_username'] ?? $pendingOAuthNickname)) : '';
        $isOAuthPending = is_array($pendingOAuth)
            && in_array($pendingOAuthProvider, ['mua', 'microsoft_minecraft'], true)
            && $pendingOAuthSub !== '';
        $isOAuthPendingWithEmail = $isOAuthPending && $pendingOAuthEmail !== '';
        $isMuaPending = $isOAuthPending && $pendingOAuthProvider === 'mua';
        $isMicrosoftMinecraftPending = $isOAuthPending && $pendingOAuthProvider === 'microsoft_minecraft';

        if ($username === '' || $email === '' || $passwordRaw === '') {
            $this->json(['success' => false, 'message' => '参数不完整'], 400);
            return;
        }

        if (!$this->whitelistSkipsRateLimits($isWhite)) {
            $cooldown = defined('AUTH_ACTION_COOLDOWN') ? (int)AUTH_ACTION_COOLDOWN : 60;
            $last = isset($_SESSION['last_auth_action']) ? (int)$_SESSION['last_auth_action'] : 0;
            if ($last > 0 && (time() - $last) < $cooldown) {
                $this->json(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
                return;
            }
        }

        if ($captchaAnswer === '' || !$this->captchaService->verify($captchaAnswer, 'register')) {
            try {
                $this->audit->logAction(null, 'REGISTER_CAPTCHA_FAILED', $ip, [
                    'email_mask' => EmailCodeService::maskEmail($email),
                    'email_hash' => EmailCodeService::hashIdentifier($email),
                    'username' => $username,
                ]);
            } catch (\Throwable $e) {
            }
            $this->json(['success' => false, 'message' => '人机验证失败，请重试'], 403);
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->json(['success' => false, 'message' => '邮箱格式不正确'], 400);
            return;
        }

        if (!$isOAuthPendingWithEmail && !$this->isEmailAllowed($email)) {
            $this->json(['success' => false, 'message' => '暂不支持该邮箱域名'], 400);
            return;
        }

        if ($isOAuthPendingWithEmail) {
            $pendingEmail = $pendingOAuthEmail;
            if (!hash_equals(strtolower($pendingEmail), strtolower($email))) {
                $this->json(['success' => false, 'message' => 'MUA 授权邮箱与注册邮箱不一致'], 400);
                return;
            }
        }

        if ($isMicrosoftMinecraftPending) {
            if ($pendingOAuthMcUuid === '' || $pendingOAuthMcUsername === '') {
                unset($_SESSION['oauth_pending_user']);
                $this->json(['success' => false, 'message' => 'Microsoft 正版登录状态已失效，请重新尝试'], 400);
                return;
            }

            $boundByUuid = $this->users->findByMinecraftUuid($pendingOAuthMcUuid);
            if ($boundByUuid) {
                unset($_SESSION['oauth_pending_user']);
                $this->json(['success' => false, 'message' => '该 Minecraft 正版账号已绑定其他网站账号'], 409);
                return;
            }

            $mcUuid = $pendingOAuthMcUuid;
            $mcName = $pendingOAuthMcUsername;
            $mcUsername = $pendingOAuthMcUsername;
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

        if (!$isOAuthPendingWithEmail) {
            if ($emailCode === '') {
                $this->json(['success' => false, 'message' => '请先发送并填写邮箱验证码'], 400);
                return;
            }

            if (!$this->emailCodeService->consumeCode($email, $emailCode, 'register')) {
                try {
                    $this->audit->logAction(null, 'REGISTER_EMAIL_CODE_FAILED', $ip, [
                        'email_mask' => EmailCodeService::maskEmail($email),
                        'email_hash' => EmailCodeService::hashIdentifier($email),
                    ]);
                } catch (\Throwable $e) {
                }

                $this->json(['success' => false, 'message' => '邮箱验证码错误或已失效'], 400);
                return;
            }
        }

        // 使用 AuthMe 原生 SHA256 算法生成哈希
        $hash = AuthMePassword::hash($passwordRaw);

        $userId = $this->users->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash,
            'role' => 'player',
            'status' => 'active',
            'email_verified' => 1,
            'mua_sub' => $isMuaPending ? $pendingOAuthSub : null,
        ]);

        $this->recordRegistration($ip);
        try {
            $this->audit->logAction(
                $userId,
                'REGISTER',
                $ip,
                ['username' => $username, 'email_mask' => EmailCodeService::maskEmail($email), 'email_hash' => EmailCodeService::hashIdentifier($email), 'success' => true]
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

        if ($isOAuthPending) {
            unset($_SESSION['oauth_pending_user']);
            $_SESSION['last_auth_action'] = time();
            if ($isMicrosoftMinecraftPending) {
                $this->json(['success' => true, 'message' => '注册成功，已完成 Microsoft 正版账号绑定']);
                return;
            }
            $this->json(['success' => true, 'message' => '注册成功，已完成 MUA 账号绑定']);
            return;
        }

        $_SESSION['last_auth_action'] = time();
        $this->json(['success' => true, 'message' => '注册成功，邮箱已验证，现在可以直接登录']);
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

    private function isEmailAllowed(string $email): bool
    {
        $enabled = $this->getSiteSetting('email_domain_whitelist_enabled', DEFAULT_EMAIL_DOMAIN_WHITELIST_ENABLED) === '1';
        $allowed = EmailDomainWhitelist::normalize(
            $this->getSiteSetting('email_domain_whitelist', DEFAULT_EMAIL_DOMAIN_WHITELIST)
        );

        return EmailDomainWhitelist::isAllowed($email, $enabled, $allowed);
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

    private function resolveCaptchaPurpose(string $purpose): string
    {
        $normalized = strtolower(trim($purpose));
        $allowed = ['login', 'register', 'forgot_password', 'email_code'];
        if (!in_array($normalized, $allowed, true)) {
            throw new \RuntimeException('Unsupported captcha purpose');
        }

        return $normalized;
    }

    private function buildCaptchaErrorSvg(string $message): string
    {
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="240" height="64" viewBox="0 0 240 64" role="img" aria-label="captcha unavailable">
  <rect width="240" height="64" rx="12" fill="#0f172a"/>
  <rect x="1" y="1" width="238" height="62" rx="11" fill="none" stroke="#fda4af" stroke-opacity="0.35"/>
  <text x="120" y="30" text-anchor="middle" fill="#fecdd3" font-size="15" font-family="Arial, Helvetica, sans-serif">captcha unavailable</text>
  <text x="120" y="48" text-anchor="middle" fill="#e2e8f0" font-size="12" font-family="Arial, Helvetica, sans-serif">{$escapedMessage}</text>
</svg>
SVG;
    }

    private function getFreshPendingOAuth(): ?array
    {
        $pendingOAuth = $_SESSION['oauth_pending_user'] ?? null;
        if (!is_array($pendingOAuth)) {
            return null;
        }

        $provider = trim((string)($pendingOAuth['provider'] ?? ''));
        $sub = trim((string)($pendingOAuth['sub'] ?? ''));
        $issuedAt = (int)($pendingOAuth['issued_at'] ?? 0);
        $isFresh = $issuedAt > 0 && (time() - $issuedAt <= 900);

        if (!in_array($provider, ['mua', 'microsoft_minecraft'], true) || $sub === '' || !$isFresh) {
            unset($_SESSION['oauth_pending_user']);
            return null;
        }

        return $pendingOAuth;
    }

    private function microsoftRequestToken(string $code): array
    {
        $clientId = trim((string)MICROSOFT_CLIENT_ID);
        $clientSecret = trim((string)MICROSOFT_CLIENT_SECRET);
        $redirectUri = trim((string)MICROSOFT_REDIRECT_URI);
        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new \RuntimeException('Microsoft 授权失败，请重新尝试');
        }

        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $response = $this->performFormRequest(
            (string)MICROSOFT_OAUTH_TOKEN_URL,
            $payload,
            'Microsoft token'
        );
        if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
            throw new \RuntimeException('Microsoft 授权失败，请重新尝试');
        }

        $data = $response['data'];
        if (trim((string)($data['access_token'] ?? '')) === '') {
            throw new \RuntimeException('Microsoft 授权失败，请重新尝试');
        }

        return $data;
    }

    private function xboxLiveAuthenticate(string $microsoftAccessToken): array
    {
        $response = $this->performJsonRequest(
            'https://user.auth.xboxlive.com/user/authenticate',
            'POST',
            [
                'Properties' => [
                    'AuthMethod' => 'RPS',
                    'SiteName' => 'user.auth.xboxlive.com',
                    'RpsTicket' => 'd=' . $microsoftAccessToken,
                ],
                'RelyingParty' => 'http://auth.xboxlive.com',
                'TokenType' => 'JWT',
            ],
            [
                'Accept: application/json',
            ],
            'Xbox Live authenticate'
        );

        if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
            throw new \RuntimeException('Xbox Live 认证失败，请确认该账号可正常使用 Xbox 服务');
        }

        $data = $response['data'];
        $token = trim((string)($data['Token'] ?? ''));
        $uhs = trim((string)($data['DisplayClaims']['xui'][0]['uhs'] ?? ''));
        if ($token === '' || $uhs === '') {
            throw new \RuntimeException('Xbox Live 认证失败，请确认该账号可正常使用 Xbox 服务');
        }

        return $data;
    }

    private function xstsAuthorize(string $xblToken): array
    {
        $response = $this->performJsonRequest(
            'https://xsts.auth.xboxlive.com/xsts/authorize',
            'POST',
            [
                'Properties' => [
                    'SandboxId' => 'RETAIL',
                    'UserTokens' => [$xblToken],
                ],
                'RelyingParty' => 'rp://api.minecraftservices.com/',
                'TokenType' => 'JWT',
            ],
            [
                'Accept: application/json',
            ],
            'XSTS authorize'
        );

        if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
            throw new \RuntimeException($this->resolveXstsAuthorizeErrorMessage($response['data']));
        }

        $data = $response['data'];
        $token = trim((string)($data['Token'] ?? ''));
        $uhs = trim((string)($data['DisplayClaims']['xui'][0]['uhs'] ?? ''));
        if ($token === '' || $uhs === '') {
            throw new \RuntimeException('XSTS 授权失败，请稍后重试');
        }

        return $data;
    }

    private function minecraftLoginWithXbox(string $uhs, string $xstsToken): array
    {
        $response = $this->performJsonRequest(
            'https://api.minecraftservices.com/authentication/login_with_xbox',
            'POST',
            [
                'identityToken' => 'XBL3.0 x=' . $uhs . ';' . $xstsToken,
            ],
            [
                'Accept: application/json',
            ],
            'Minecraft login_with_xbox'
        );

        if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
            throw new \RuntimeException('Minecraft 服务登录失败，请稍后重试');
        }

        $data = $response['data'];
        if (trim((string)($data['access_token'] ?? '')) === '') {
            throw new \RuntimeException('Minecraft 服务登录失败，请稍后重试');
        }

        return $data;
    }

    private function minecraftRequestProfile(string $minecraftAccessToken): array
    {
        $response = $this->performJsonRequest(
            'https://api.minecraftservices.com/minecraft/profile',
            'GET',
            null,
            [
                'Accept: application/json',
                'Authorization: Bearer ' . $minecraftAccessToken,
            ],
            'Minecraft profile'
        );

        if (in_array($response['http_code'], [403, 404], true)) {
            throw new \RuntimeException('该 Microsoft 账号未拥有 Minecraft Java Edition，或暂时无法读取正版档案。');
        }
        if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
            throw new \RuntimeException('Minecraft 档案读取失败，请稍后重试');
        }

        $data = $response['data'];
        if (trim((string)($data['id'] ?? '')) === '' || trim((string)($data['name'] ?? '')) === '') {
            throw new \RuntimeException('该 Microsoft 账号未拥有 Minecraft Java Edition，或暂时无法读取正版档案。');
        }

        return $data;
    }

    private function performFormRequest(string $url, array $payload, string $context): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException($context . ' request initialization failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($resp)) {
            throw new \RuntimeException($context . ' request failed: ' . $error);
        }

        $data = [];
        if ($resp !== '') {
            $data = json_decode($resp, true);
            if (!is_array($data)) {
                throw new \RuntimeException($context . ' response parse failed');
            }
        }

        return [
            'http_code' => $httpCode,
            'data' => $data,
        ];
    }

    private function performJsonRequest(string $url, string $method, ?array $payload, array $headers, string $context): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException($context . ' request initialization failed');
        }

        $requestHeaders = $headers;
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($payload !== null) {
            $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if (!is_string($encodedPayload)) {
                throw new \RuntimeException($context . ' request build failed');
            }

            $options[CURLOPT_POSTFIELDS] = $encodedPayload;
            $requestHeaders[] = 'Content-Type: application/json';
        }

        if ($requestHeaders !== []) {
            $options[CURLOPT_HTTPHEADER] = $requestHeaders;
        }

        curl_setopt_array($ch, $options);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($resp)) {
            throw new \RuntimeException($context . ' request failed: ' . $error);
        }

        $data = [];
        if ($resp !== '') {
            $data = json_decode($resp, true);
            if (!is_array($data)) {
                throw new \RuntimeException($context . ' response parse failed');
            }
        }

        return [
            'http_code' => $httpCode,
            'data' => $data,
        ];
    }

    private function resolveXstsAuthorizeErrorMessage(array $data): string
    {
        $xerr = trim((string)($data['XErr'] ?? ''));
        if ($xerr === '2148916233') {
            return '该账号没有 Xbox 档案，可能需要先登录 Xbox 官网初始化资料。';
        }
        if ($xerr === '2148916235') {
            return '该账号地区不可用或存在地区限制。';
        }
        if (in_array($xerr, ['2148916236', '2148916237'], true)) {
            return '该账号受年龄或家长控制限制，暂时无法完成 Xbox 授权。';
        }

        return 'XSTS 授权失败，请稍后重试';
    }

    private function resolveMicrosoftMinecraftProductionErrorMessage(string $message): string
    {
        $safeMessages = [
            '登录状态校验失败，请重新尝试',
            'Microsoft 授权失败，请重新尝试',
            'Xbox Live 认证失败，请确认该账号可正常使用 Xbox 服务',
            '该账号没有 Xbox 档案，可能需要先登录 Xbox 官网初始化资料。',
            '该账号地区不可用或存在地区限制。',
            '该账号受年龄或家长控制限制，暂时无法完成 Xbox 授权。',
            'XSTS 授权失败，请稍后重试',
            'Minecraft 服务登录失败，请稍后重试',
            'Minecraft 档案读取失败，请稍后重试',
            '该 Microsoft 账号未拥有 Minecraft Java Edition，或暂时无法读取正版档案。',
            '该 Minecraft 正版账号已绑定其他网站账号，无法重复绑定',
            '该 Minecraft 正版账号已绑定其他网站账号',
            '当前账号状态异常，无法绑定正版账号',
        ];
        if (in_array($message, $safeMessages, true)) {
            return $message;
        }
        if (str_contains($message, 'Xbox Live')) {
            return 'Xbox Live 认证失败，请确认该账号可正常使用 Xbox 服务';
        }
        if (str_contains($message, 'XSTS')) {
            return 'XSTS 授权失败，请稍后重试';
        }
        if (str_contains($message, 'Minecraft')) {
            return '该 Microsoft 账号未拥有 Minecraft Java Edition，或暂时无法读取正版档案。';
        }

        return 'Microsoft 正版登录失败，请稍后重试';
    }

    private function isDevelopmentEnvironment(): bool
    {
        return strtolower((string)(defined('APP_ENV') ? APP_ENV : 'production')) === 'development';
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
