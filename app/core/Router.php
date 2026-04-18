<?php
declare(strict_types=1);

namespace Core;

class Router
{
    /**
     * @var array<string,array<string,array{0:string,1:string}>>
     */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function any(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void
    {
        $this->routes[$method][$path] = [$handler[0], $handler[1]];
    }

    /**
     * @return array{0:string,1:string,2:array}
     */
    public function resolve(string $uri, string $method): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($method);

        $routes = $this->routes[$method] ?? [];

        // Exact match
        if (isset($routes[$path])) {
            return [$routes[$path][0], $routes[$path][1], []];
        }

        // TODO: add param routes if needed

        // Default: 404 controller
        return [\Controller\HomeController::class, 'notFound', []];
    }
}

