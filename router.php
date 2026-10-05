<?php

declare(strict_types=1);

/*
 * Front controller for community pages. Apache routes here only when no physical
 * file or directory matches, so existing calculators, guides, and references
 * always take precedence over dynamic routes.
 */

try {
  $app = require __DIR__ . '/src/bootstrap.php';
} catch (Throwable $error) {
  error_log("UV's Compendium: application bootstrap failed");
  http_response_code(503);
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Temporarily unavailable</title></head>'
    . '<body><h1>Temporarily unavailable</h1><p>Community features are temporarily unavailable.</p></body></html>';
  return;
}

(new Uvs\Http\Kernel($app))->handle(Uvs\Http\Request::fromGlobals())->send();
