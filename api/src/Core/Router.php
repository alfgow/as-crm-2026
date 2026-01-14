<?php
namespace App\Core;

final class Router {
  /** @var array<string, array<int, array{pattern:string, handler:callable}>> */
  private array $routes = [];

  public function add(string $method, string $pattern, callable $handler): void {
    $method = strtoupper($method);
    $this->routes[$method][] = ['pattern' => $pattern, 'handler' => $handler];
  }

  /**
   * @return array{handler:callable, params:array<string,string>}|null
   */
  public function match(string $method, string $path): ?array {
    $method = strtoupper($method);
    $list = $this->routes[$method] ?? [];
    foreach ($list as $r) {
      $regex = $this->toRegex($r['pattern']);
      if (preg_match($regex, $path, $m)) {
        $params = [];
        foreach ($m as $k => $v) {
          if (is_string($k)) $params[$k] = $v;
        }
        return ['handler' => $r['handler'], 'params' => $params];
      }
    }
    return null;
  }

  private function toRegex(string $pattern): string {
    // /api/v1/users/{id} -> named group
    $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
    return '#^' . $regex . '$#';
  }
}
