<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Core\CaptchaService;
use Core\EmailDomainWhitelist;
use Core\SensitiveDataSanitizer;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_id('auth-security-primitives');
    session_start();
}

$_SESSION = [];

$captchaCacheFile = BASE_PATH . '/storage/cache/captcha_refresh_' . md5('register|203.0.113.81') . '.json';
if (is_file($captchaCacheFile)) {
    @unlink($captchaCacheFile);
}

$captcha = new CaptchaService([
    'ttl_seconds' => 300,
    'refresh_cooldown_seconds' => 1,
    'refresh_window_seconds' => 60,
    'max_refreshes_per_window' => 5,
]);

$issued = $captcha->issue('register', '203.0.113.81');
assertTrueCondition(isset($issued['svg']) && is_string($issued['svg']) && $issued['svg'] !== '', 'CaptchaService should return an SVG payload.');

$svg = (string)$issued['svg'];
preg_match('/(\d+)\s*([+\-])\s*(\d+)/', $svg, $captchaMatches);
assertTrueCondition(count($captchaMatches) === 4, 'Captcha SVG should expose a math expression.');
$left = (int)$captchaMatches[1];
$operator = (string)$captchaMatches[2];
$right = (int)$captchaMatches[3];
$answer = $operator === '-' ? ($left - $right) : ($left + $right);

assertTrueCondition($captcha->verify((string)$answer, 'register') === true, 'CaptchaService should accept the correct answer.');
assertTrueCondition($captcha->verify((string)$answer, 'register') === false, 'CaptchaService should invalidate a challenge after successful verification.');

$normalizedDomains = EmailDomainWhitelist::normalize(" QQ.com, foxmail.com ,sub.example.com,,qq.com,https://evil.com/a ");
assertSameValue(
    ['qq.com', 'foxmail.com', 'sub.example.com'],
    $normalizedDomains,
    'EmailDomainWhitelist should normalize, deduplicate, lowercase, and drop invalid entries.'
);

assertTrueCondition(
    EmailDomainWhitelist::isAllowed('Player@FoxMail.com', true, $normalizedDomains) === true,
    'EmailDomainWhitelist should allow normalized domains when whitelist mode is enabled.'
);
assertTrueCondition(
    EmailDomainWhitelist::isAllowed('player@evil.com', true, $normalizedDomains) === false,
    'EmailDomainWhitelist should reject domains outside the configured whitelist.'
);
assertTrueCondition(
    EmailDomainWhitelist::isAllowed('player@evil.com', false, $normalizedDomains) === true,
    'EmailDomainWhitelist should allow all domains when the whitelist switch is disabled.'
);

$sanitized = SensitiveDataSanitizer::sanitize([
    'password' => 'secret-pass',
    'password_hash' => 'hash-value',
    'token' => 'reset-token',
    'csrf_token' => 'csrf-value',
    'smtp_password' => 'smtp-secret',
    'email_code' => '123456',
    'captcha_answer' => '8',
    'remember_me' => '1',
    'Authorization' => 'Bearer abc',
    'x-api-token' => 'abc-token',
    'nested' => [
        'safe' => 'value',
        'password' => 'nested-secret',
    ],
]);

assertSameValue('[REDACTED]', $sanitized['password'], 'SensitiveDataSanitizer should redact passwords.');
assertSameValue('[REDACTED]', $sanitized['token'], 'SensitiveDataSanitizer should redact tokens.');
assertSameValue('[REDACTED]', $sanitized['email_code'], 'SensitiveDataSanitizer should redact email codes.');
assertSameValue('[REDACTED]', $sanitized['nested']['password'], 'SensitiveDataSanitizer should redact nested sensitive values.');
assertSameValue('value', $sanitized['nested']['safe'], 'SensitiveDataSanitizer should preserve safe nested values.');

echo "auth_security_primitives: PASS\n";
