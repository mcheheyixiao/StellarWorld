<?php
declare(strict_types=1);

// Front controller

// Enable strict error reporting in development
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');

// Simple autoloader for core, controllers, models
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Core\\' => APP_PATH . '/core/',
        'Controller\\' => APP_PATH . '/controllers/',
        'Model\\' => APP_PATH . '/models/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Load Composer autoloader
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Load config
require_once APP_PATH . '/config/config.php';

use Core\Router;

// Start session
session_start();

// Dispatch request
$router = new Router();
require APP_PATH . '/config/routes.php';

[$controllerClass, $action, $params] = $router->resolve($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

if (!class_exists($controllerClass)) {
    http_response_code(404);
    echo 'Controller not found';
    exit;
}

$controller = new $controllerClass();

if (!method_exists($controller, $action)) {
    http_response_code(404);
    echo 'Action not found';
    exit;
}

echo call_user_func_array([$controller, $action], $params);

