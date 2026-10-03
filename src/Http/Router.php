<?php

declare(strict_types=1);

namespace Uvs\Http;


/**
 * Small explicit route table. Patterns use {name} placeholders with optional
 * {name:regex} constraints. Every application URL ends in a slash, matching the
 * site's directory-style URLs; slashless requests are redirected.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: array{0: class-string, 1: string}, names: list<string>}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $names = [];
        $regex = preg_replace_callback('/\{([a-z_]+)(?::([^}]+))?\}/', static function (array $match) use (&$names): string {
            $names[] = $match[1];
            return '(' . ($match[2] ?? '[^/]+') . ')';
        }, $pattern);
        $this->routes[] = ['method' => $method, 'regex' => '#^' . $regex . '$#D', 'handler' => $handler, 'names' => $names];
    }

    /**
     * @return array{0: ?array, 1: array<string, string>, 2: list<string>} handler, parameters, allowed methods
     */
    public function match(string $method, string $path): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $match)) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = [];
            foreach ($route['names'] as $index => $name) {
                $params[$name] = $match[$index + 1];
            }
            return [$route['handler'], $params, []];
        }
        return [null, [], array_values(array_unique($allowed))];
    }

    /** Whether the slash-terminated form of a path is a GET route. */
    public function hasCanonicalSlash(string $path): bool
    {
        if (str_ends_with($path, '/')) {
            return false;
        }
        [$handler] = $this->match('GET', $path . '/');
        return $handler !== null;
    }
}
