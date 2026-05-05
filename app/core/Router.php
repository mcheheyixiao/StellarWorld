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

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
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

        // Parameterized match: /resource/{id}
        foreach ($routes as $routePath => $handler) {
            if (!str_contains($routePath, '{')) {
                continue;
            }

            $params = $this->matchParameterizedRoute($routePath, $path);
            if ($params === null) {
                continue;
            }

            return [$handler[0], $handler[1], $params];
        }

        // Default: 404 controller
        return [\Controller\HomeController::class, 'notFound', []];
    }

    /**
     * @return array<int,string>|null
     */
    private function matchParameterizedRoute(string $routePath, string $actualPath): ?array
    {
        if ($routePath === '/' || $actualPath === '/') {
            return $routePath === $actualPath ? [] : null;
        }

        $routeSegments = explode('/', trim($routePath, '/'));
        $actualSegments = explode('/', trim($actualPath, '/'));

        if (count($routeSegments) !== count($actualSegments)) {
            return null;
        }

        $params = [];
        foreach ($routeSegments as $idx => $routeSegment) {
            $actualSegment = $actualSegments[$idx];
            if (preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', $routeSegment) === 1) {
                $params[] = rawurldecode($actualSegment);
                continue;
            }

            if ($routeSegment !== $actualSegment) {
                return null;
            }
        }

        return $params;
    }
}

