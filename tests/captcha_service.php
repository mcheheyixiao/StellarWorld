<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_id('captcha-service-test');
    session_start();
}

$_SESSION = [];

$routesSource = file_get_contents(BASE_PATH . '/app/config/routes.php');
assertTrueCondition(
    is_string($routesSource) && str_contains($routesSource, "/auth/captcha"),
    'Routes should expose the /auth/captcha endpoint.'
);

$authControllerSource = file_get_contents(BASE_PATH . '/app/controllers/AuthController.php');
$legacyCaptchaField = 'cf' . '-turnstile-response';
$legacyVerifier = 'verify' . 'Turnstile(';
assertTrueCondition(
    is_string($authControllerSource) && !str_contains($authControllerSource, $legacyCaptchaField),
    'AuthController should no longer depend on the legacy captcha field during Step 3.'
);
assertTrueCondition(
    is_string($authControllerSource) && !str_contains($authControllerSource, $legacyVerifier),
    'AuthController should no longer call the legacy remote verifier during Step 3.'
);

$captcha = new Core\CaptchaService([
    'ttl_seconds' => 300,
    'refresh_cooldown_seconds' => 1,
    'refresh_window_seconds' => 60,
    'max_refreshes_per_window' => 5,
]);

$issued = $captcha->issue('register', '203.0.113.8');
assertTrueCondition(isset($issued['svg']) && is_string($issued['svg']) && $issued['svg'] !== '', 'CaptchaService should return an SVG payload.');

$svg = (string)$issued['svg'];
preg_match('/(\d+)\s*([+\-])\s*(\d+)/', $svg, $matches);
assertTrueCondition(count($matches) === 4, 'Captcha SVG should embed a math expression.');
$left = (int)$matches[1];
$operator = (string)$matches[2];
$right = (int)$matches[3];
$answer = $operator === '-' ? ($left - $right) : ($left + $right);

assertTrueCondition($captcha->verify((string)$answer, 'register') === true, 'CaptchaService should accept the correct answer.');
assertTrueCondition($captcha->verify((string)$answer, 'register') === false, 'CaptchaService should invalidate the challenge after a successful verification.');
assertTrueCondition($captcha->verify((string)$answer, 'login') === false, 'CaptchaService should reject answers under a different purpose namespace.');

echo "captcha_service: PASS\n";
