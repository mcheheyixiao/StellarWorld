<?php
$tests = [
    ['uri' => '/profile', 'method' => 'GET', 'accept' => 'text/html'],
    ['uri' => '/admin', 'method' => 'GET', 'accept' => 'text/html'],
    ['uri' => '/profile/password/update', 'method' => 'POST', 'accept' => 'application/json'],
];

foreach ($tests as $t) {
    $_GET = [];
    $_POST = [];
    $_COOKIE = [];
    $_SESSION = [];
    $_SERVER = [
        'REQUEST_URI' => $t['uri'],
        'REQUEST_METHOD' => $t['method'],
        'HTTP_ACCEPT' => $t['accept'],
        'SERVER_PORT' => '80',
        'HTTPS' => 'off',
    ];

    ob_start();
    include __DIR__ . '/../public/index.php';
    $out = ob_get_clean();

    echo "=== {$t['method']} {$t['uri']} ===\n";
    echo 'prefix=' . substr(str_replace(["\r", "\n"], ' ', (string)$out), 0, 140) . "\n\n";
}
