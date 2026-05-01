<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = BASE_PATH;

$routesSource = file_get_contents($root . '/app/config/routes.php');
assertTrueCondition(
    is_string($routesSource) && str_contains($routesSource, "/auth/email-code/send"),
    'Routes should expose the /auth/email-code/send endpoint.'
);

$authControllerSource = file_get_contents($root . '/app/controllers/AuthController.php');
assertTrueCondition(
    is_string($authControllerSource) && str_contains($authControllerSource, 'EmailCodeService'),
    'AuthController should depend on EmailCodeService for register email-code flows.'
);
assertTrueCondition(
    is_string($authControllerSource) && str_contains($authControllerSource, 'sendEmailCode'),
    'AuthController should provide a sendEmailCode() endpoint.'
);
assertTrueCondition(
    is_string($authControllerSource) && !str_contains($authControllerSource, '$this->sendVerificationEmail($userId, $email);'),
    'Register flow should stop sending legacy verification-link emails.'
);

$registerViewSource = file_get_contents($root . '/app/views/auth/register.php');
assertTrueCondition(
    is_string($registerViewSource) && str_contains($registerViewSource, 'name="email_code"'),
    'Register page should collect the SMTP email verification code.'
);
assertTrueCondition(
    is_string($registerViewSource) && str_contains($registerViewSource, '/auth/email-code/send'),
    'Register page should call the email-code send endpoint.'
);

$legacyRuntimeUrl = 'https://challenges.cloudflare.com/' . 'turnstile';
$legacyMarkup = 'cf' . '-turnstile';
foreach ([
    '/app/views/auth/login.php',
    '/app/views/auth/register.php',
    '/app/views/auth/forgot_password.php',
] as $relativePath) {
    $source = file_get_contents($root . $relativePath);
    assertTrueCondition(
        is_string($source) && !str_contains($source, $legacyRuntimeUrl),
        basename($relativePath) . ' should no longer load the Cloudflare Turnstile runtime.'
    );
    assertTrueCondition(
        is_string($source) && !str_contains($source, $legacyMarkup),
        basename($relativePath) . ' should no longer render Turnstile markup.'
    );
}

$auditModelSource = file_get_contents($root . '/app/models/AuditModel.php');
assertTrueCondition(
    is_string($auditModelSource) && str_contains($auditModelSource, 'runtime-audit-'),
    'AuditModel should support JSONL file audit storage.'
);
assertTrueCondition(
    is_string($auditModelSource) && str_contains($auditModelSource, 'SensitiveDataSanitizer'),
    'AuditModel should sanitize sensitive fields before persistence.'
);
assertTrueCondition(
    is_string($auditModelSource) && str_contains($auditModelSource, 'audit_log_storage'),
    'AuditModel should read the audit_log_storage setting.'
);

$adminControllerSource = file_get_contents($root . '/app/controllers/AdminController.php');
assertTrueCondition(
    is_string($adminControllerSource) && str_contains($adminControllerSource, 'email_domain_whitelist'),
    'AdminController should validate and persist the email_domain_whitelist setting.'
);
assertTrueCondition(
    is_string($adminControllerSource) && str_contains($adminControllerSource, 'audit_log_storage'),
    'AdminController should validate and persist the audit_log_storage setting.'
);

echo "auth_verification_replacement: PASS\n";
