<?php
declare(strict_types=1);

namespace Core;

use PDO;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class EmailCodeService
{
    private PDO $db;

    /**
     * @var array<string,string>|null
     */
    private ?array $settingsCache = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function sendCode(string $email, string $purpose, string $ipAddress, string $userAgent = ''): void
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPurpose = $this->normalizePurpose($purpose);
        $now = time();

        $this->assertSendAllowed($normalizedEmail, $normalizedPurpose, $ipAddress, $now);

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', $now + $this->getExpireSeconds());
        $payload = $this->buildVerificationPayload($normalizedEmail, $normalizedPurpose, $code);
        $codeHash = password_hash($payload, PASSWORD_DEFAULT);

        if (!is_string($codeHash) || $codeHash === '') {
            throw new \RuntimeException('验证码生成失败，请稍后重试');
        }

        $stmt = $this->db->prepare('
            INSERT INTO email_verifications (
                user_id, token, purpose, email, code_hash, expires_at, attempts, ip_hash, user_agent_hash, created_at, used
            ) VALUES (
                NULL, NULL, :purpose, :email, :code_hash, :expires_at, 0, :ip_hash, :user_agent_hash, NOW(), 0
            )
        ');
        $stmt->execute([
            ':purpose' => $normalizedPurpose,
            ':email' => $normalizedEmail,
            ':code_hash' => $codeHash,
            ':expires_at' => $expiresAt,
            ':ip_hash' => $this->hashValue($ipAddress),
            ':user_agent_hash' => $this->hashValue($userAgent),
        ]);

        $verificationId = (int)$this->db->lastInsertId();

        try {
            $this->sendMail($normalizedEmail, $code, $this->getExpireSeconds());
        } catch (\Throwable $e) {
            if ($verificationId > 0) {
                try {
                    $cleanup = $this->db->prepare('DELETE FROM email_verifications WHERE id = :id AND used = 0');
                    $cleanup->execute([':id' => $verificationId]);
                } catch (\Throwable $cleanupError) {
                }
            }

            throw $e;
        }
    }

    public function consumeCode(string $email, string $code, string $purpose): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPurpose = $this->normalizePurpose($purpose);
        $normalizedCode = trim($code);
        if ($normalizedCode === '' || preg_match('/^\d{6}$/', $normalizedCode) !== 1) {
            return false;
        }

        $stmt = $this->db->prepare('
            SELECT id, code_hash, expires_at, attempts
            FROM email_verifications
            WHERE email = :email
              AND purpose = :purpose
              AND used = 0
              AND code_hash IS NOT NULL
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute([
            ':email' => $normalizedEmail,
            ':purpose' => $normalizedPurpose,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }

        $rowId = (int)($row['id'] ?? 0);
        $attempts = (int)($row['attempts'] ?? 0);
        $expiresAt = strtotime((string)($row['expires_at'] ?? ''));
        $codeHash = (string)($row['code_hash'] ?? '');
        if ($rowId <= 0 || $codeHash === '' || $expiresAt === false || $expiresAt < time() || $attempts >= 5) {
            $this->expireRow($rowId, $attempts);
            return false;
        }

        $payload = $this->buildVerificationPayload($normalizedEmail, $normalizedPurpose, $normalizedCode);
        if (!password_verify($payload, $codeHash)) {
            $this->incrementAttempts($rowId, $attempts + 1);
            return false;
        }

        $update = $this->db->prepare('UPDATE email_verifications SET used = 1, used_at = NOW() WHERE id = :id AND used = 0');
        $update->execute([':id' => $rowId]);

        return $update->rowCount() > 0;
    }

    public static function maskEmail(string $email): string
    {
        $normalizedEmail = trim($email);
        if ($normalizedEmail === '' || !str_contains($normalizedEmail, '@')) {
            return '[invalid-email]';
        }

        [$local, $domain] = explode('@', $normalizedEmail, 2);
        $local = trim($local);
        $domain = trim($domain);
        if ($local === '' || $domain === '') {
            return '[invalid-email]';
        }

        $prefix = strtolower(substr($local, 0, 1));
        $suffix = strlen($local) > 1 ? strtolower(substr($local, -1)) : '';
        return $prefix . '***' . $suffix . '@' . strtolower($domain);
    }

    public static function hashIdentifier(string $value): string
    {
        return hash('sha256', trim($value));
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('邮箱格式不正确');
        }

        return $normalized;
    }

    private function normalizePurpose(string $purpose): string
    {
        $normalized = strtolower(trim($purpose));
        if (!in_array($normalized, ['register'], true)) {
            throw new \RuntimeException('不支持的验证码用途');
        }

        return $normalized;
    }

    private function buildVerificationPayload(string $email, string $purpose, string $code): string
    {
        return $purpose . '|' . strtolower($email) . '|' . trim($code);
    }

    private function getExpireSeconds(): int
    {
        return max(60, (int)$this->getSetting('email_code_expire_seconds', (string)DEFAULT_EMAIL_CODE_EXPIRE_SECONDS));
    }

    private function getSendCooldownSeconds(): int
    {
        return max(30, (int)$this->getSetting('email_code_send_cooldown_seconds', (string)DEFAULT_EMAIL_CODE_SEND_COOLDOWN_SECONDS));
    }

    private function getIpHourlyLimit(): int
    {
        return max(1, defined('EMAIL_CODE_IP_HOURLY_LIMIT') ? (int)EMAIL_CODE_IP_HOURLY_LIMIT : 10);
    }

    private function getEmailDailyLimit(): int
    {
        return max(1, defined('EMAIL_CODE_EMAIL_DAILY_LIMIT') ? (int)EMAIL_CODE_EMAIL_DAILY_LIMIT : 10);
    }

    private function assertSendAllowed(string $email, string $purpose, string $ipAddress, int $now): void
    {
        $cooldownStmt = $this->db->prepare('
            SELECT created_at
            FROM email_verifications
            WHERE email = :email
              AND purpose = :purpose
              AND code_hash IS NOT NULL
            ORDER BY id DESC
            LIMIT 1
        ');
        $cooldownStmt->execute([
            ':email' => $email,
            ':purpose' => $purpose,
        ]);
        $lastRow = $cooldownStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($lastRow)) {
            $lastSentAt = strtotime((string)($lastRow['created_at'] ?? ''));
            if ($lastSentAt !== false && ($now - $lastSentAt) < $this->getSendCooldownSeconds()) {
                throw new \RuntimeException('验证码发送过于频繁，请稍后再试');
            }
        }

        $ipStmt = $this->db->prepare('
            SELECT COUNT(*) AS c
            FROM email_verifications
            WHERE ip_hash = :ip_hash
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
              AND code_hash IS NOT NULL
        ');
        $ipStmt->execute([':ip_hash' => $this->hashValue($ipAddress)]);
        $ipCount = (int)($ipStmt->fetchColumn() ?: 0);
        if ($ipCount >= $this->getIpHourlyLimit()) {
            throw new \RuntimeException('当前 IP 发送次数过多，请稍后再试');
        }

        $emailStmt = $this->db->prepare('
            SELECT COUNT(*) AS c
            FROM email_verifications
            WHERE email = :email
              AND purpose = :purpose
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
              AND code_hash IS NOT NULL
        ');
        $emailStmt->execute([
            ':email' => $email,
            ':purpose' => $purpose,
        ]);
        $emailCount = (int)($emailStmt->fetchColumn() ?: 0);
        if ($emailCount >= $this->getEmailDailyLimit()) {
            throw new \RuntimeException('该邮箱今日发送次数已达上限');
        }
    }

    private function incrementAttempts(int $rowId, int $attempts): void
    {
        if ($rowId <= 0) {
            return;
        }

        $sql = 'UPDATE email_verifications SET attempts = :attempts';
        if ($attempts >= 5) {
            $sql .= ', used = 1, used_at = NOW()';
        }
        $sql .= ' WHERE id = :id AND used = 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attempts' => $attempts,
            ':id' => $rowId,
        ]);
    }

    private function expireRow(int $rowId, int $attempts): void
    {
        if ($rowId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE email_verifications SET attempts = :attempts, used = 1, used_at = NOW() WHERE id = :id AND used = 0');
        $stmt->execute([
            ':attempts' => max($attempts, 5),
            ':id' => $rowId,
        ]);
    }

    private function hashValue(string $value): string
    {
        return hash('sha256', trim($value));
    }

    private function sendMail(string $email, string $code, int $expireSeconds): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        $expireMinutes = max(1, (int)ceil($expireSeconds / 60));
        $subject = '繁星World 邮箱验证码';
        $body = sprintf(
            '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head><body style="margin:0;padding:24px;background:#020617;color:#e2e8f0;font-family:Arial,Helvetica,sans-serif;"><div style="max-width:560px;margin:0 auto;padding:32px;border-radius:18px;background:rgba(15,23,42,.96);border:1px solid rgba(103,232,249,.25);"><h1 style="margin:0 0 16px;font-size:24px;color:#e0f2fe;">邮箱验证码</h1><p style="margin:0 0 12px;color:#cbd5e1;line-height:1.7;">你正在为繁星World 注册账号，请在页面中输入以下 6 位验证码：</p><div style="margin:24px 0;padding:18px 20px;border-radius:14px;background:rgba(14,165,233,.14);border:1px solid rgba(34,211,238,.35);font-size:32px;font-weight:700;letter-spacing:8px;text-align:center;color:#67e8f9;">%s</div><p style="margin:0;color:#94a3b8;line-height:1.7;">验证码 %d 分钟内有效，仅可使用一次。若不是你本人操作，请忽略此邮件。</p></div></body></html>',
            htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            $expireMinutes
        );

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = (SMTP_PORT === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME !== '' ? SMTP_FROM_NAME : '繁星World');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new \RuntimeException('验证码邮件发送失败，请稍后重试');
        }
    }

    private function getSetting(string $key, string $default = ''): string
    {
        if ($this->settingsCache === null) {
            $this->settingsCache = [];
            try {
                $stmt = $this->db->query('SELECT setting_key, setting_value FROM site_settings');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $settingKey = (string)($row['setting_key'] ?? '');
                    if ($settingKey !== '') {
                        $this->settingsCache[$settingKey] = (string)($row['setting_value'] ?? '');
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return array_key_exists($key, $this->settingsCache) ? $this->settingsCache[$key] : $default;
    }
}
