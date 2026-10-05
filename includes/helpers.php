<?php

declare(strict_types=1);

/*
 * Global view helpers shared by the static pages and the community application.
 * Every function is guarded so the file can be included from either entry point.
 */

if (!function_exists('h')) {
  function h(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

if (!function_exists('site_url')) {
  function site_url(string $path = ''): string
  {
    global $base_path;
    $base = is_string($base_path ?? null) ? $base_path : '';

    if ($path === '') {
      return h($base !== '' ? $base : './');
    }

    return h($base . ltrim($path, '/'));
  }
}

if (!function_exists('asset_version')) {
  function asset_version(string $path): int
  {
    $full_path = dirname(__DIR__) . '/' . ltrim($path, '/');

    return is_file($full_path) ? (int) filemtime($full_path) : time();
  }
}

if (!function_exists('guide_updated_date')) {
  function guide_updated_date(string $guide_path, string $source_file): string
  {
    $manifest = __DIR__ . '/guide-update-dates.php';
    if (is_file($manifest)) {
      $dates = require $manifest;
      if (is_array($dates) && isset($dates[$guide_path])) {
        return $dates[$guide_path];
      }
    }

    // The source checkout has no build manifest; show its local file date.
    $modified = filemtime($source_file);
    if ($modified === false) {
      throw new RuntimeException('Cannot read guide modification time');
    }
    return date('Y-m-d', $modified);
  }
}

if (!function_exists('uvs_content_security_policy')) {
  /**
   * One site-wide policy covering the calculators' ES modules, privacy-enhanced
   * YouTube embeds, Cloudflare Turnstile, and locally served media. Inline styles
   * remain allowed for existing guide markup; inline scripts are not.
   */
  function uvs_content_security_policy(): string
  {
    return implode('; ', [
      "default-src 'self'",
      "script-src 'self' https://challenges.cloudflare.com",
      "style-src 'self' 'unsafe-inline'",
      "img-src 'self' data:",
      "font-src 'self'",
      "connect-src 'self'",
      "media-src 'self'",
      'frame-src https://www.youtube-nocookie.com https://challenges.cloudflare.com',
      "object-src 'none'",
      "base-uri 'self'",
      "form-action 'self'",
      "frame-ancestors 'self'",
    ]);
  }
}

if (!function_exists('uvs_security_headers')) {
  function uvs_security_headers(): void
  {
    if (headers_sent()) {
      return;
    }
    header('Content-Security-Policy: ' . uvs_content_security_policy());
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
  }
}

if (!function_exists('uvs_page_context')) {
  /**
   * Viewer context for the shared page header. Static pages must keep working
   * when the community application is unconfigured, its dependencies are absent,
   * or the database is down, so every failure degrades to an anonymous visitor.
   * Anonymous visitors without a session cookie never trigger a database query.
   *
   * @return array{user: ?array<string, mixed>, csrf: ?string, available: bool, admin_counts: array<string, int>}
   */
  function uvs_page_context(): array
  {
    static $context = null;
    if ($context !== null) {
      return $context;
    }
    $context = ['user' => null, 'csrf' => null, 'available' => false, 'admin_counts' => []];
    try {
      $app = class_exists(\Uvs\App::class, false)
        ? \Uvs\App::instance()
        : require dirname(__DIR__) . '/src/bootstrap.php';
      if (!$app instanceof \Uvs\App || !$app->config->isUsable()) {
        return $context;
      }
      $context['available'] = true;
      $user = $app->auth()->user();
      if ($user !== null) {
        $context['user'] = $user;
        $context['csrf'] = (new \Uvs\Security\Csrf($app->session()))->token();
        if ($app->gate()->allows($user, 'admin.access')) {
          $context['admin_counts'] = [
            'users' => (int) $app->db()->value("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
            'guides' => (int) $app->db()->value("SELECT COUNT(*) FROM guides WHERE review_status = 'in_review' AND deleted_at IS NULL"),
          ];
        }
      }
    } catch (\Throwable $error) {
      $context['user'] = null;
      $context['csrf'] = null;
      if (class_exists(\Uvs\App::class, false)) {
        try {
          \Uvs\App::instance()->logger()->exception($error, 'Page header context unavailable');
        } catch (\Throwable) {
          error_log('UV\'s Compendium: page header context unavailable');
        }
      }
    }
    return $context;
  }
}

if (!function_exists('uvs_account_status_label')) {
  /**
   * @param array<string, mixed> $user
   */
  function uvs_account_status_label(array $user): string
  {
    if (($user['status'] ?? '') === 'active') {
      return match ($user['role'] ?? 'member') {
        'admin' => 'Administrator',
        'moderator' => 'Moderator',
        default => 'Member',
      };
    }
    return match ($user['status'] ?? '') {
      'pending' => 'Awaiting approval',
      'suspended' => 'Suspended',
      'rejected' => 'Not approved',
      default => 'Member',
    };
  }
}

if (!function_exists('uvs_avatar')) {
  /**
   * Prints an avatar: the member's uploaded image, or a deterministic initial
   * on a themed tile. Expects optional avatar_public_id / avatar_extension keys.
   *
   * @param array<string, mixed> $user
   */
  function uvs_avatar(array $user, string $size = 'md', bool $decorative = true): void
  {
    $name = (string) ($user['username'] ?? '?');
    $class = 'avatar avatar-' . preg_replace('/[^a-z]/', '', $size);
    $alt = $decorative ? '' : $name . "'s avatar";
    $publicId = $user['avatar_public_id'] ?? null;
    if (is_string($publicId) && preg_match('/^[A-Za-z0-9_-]{22}$/', $publicId)) {
      $extension = in_array($user['avatar_extension'] ?? 'webp', ['webp', 'jpg', 'png'], true) ? $user['avatar_extension'] : 'webp';
      echo '<img class="' . h($class) . '" src="' . site_url('media/' . $publicId . '.' . $extension) . '" alt="' . h($alt)
        . '" width="96" height="96" loading="lazy" decoding="async">';
      return;
    }
    $hue = hexdec(substr(hash('crc32b', strtolower($name)), 0, 4)) % 360;
    $initial = strtoupper(substr($name, 0, 1));
    echo '<span class="' . h($class) . ' avatar-initial" data-hue="' . (int) round($hue / 30) . '"'
      . ($decorative ? ' aria-hidden="true"' : ' role="img" aria-label="' . h($alt) . '"') . '>' . h($initial) . '</span>';
  }
}

if (!function_exists('uvs_published_community_guides')) {
  /**
   * Published community guides for the static guide index, newest first.
   * Returns null when the community application is unavailable so the curated
   * catalog still renders on its own.
   *
   * @return list<array<string, mixed>>|null
   */
  function uvs_published_community_guides(int $limit = 60): ?array
  {
    try {
      $context = uvs_page_context();
      if (!$context['available']) {
        return null;
      }
      return \Uvs\App::instance()->guides()->listPublished($limit);
    } catch (\Throwable $error) {
      try {
        \Uvs\App::instance()->logger()->exception($error, 'Community guide list unavailable');
      } catch (\Throwable) {
        error_log("UV's Compendium: community guide list unavailable");
      }
      return null;
    }
  }
}

if (!function_exists('uvs_render_flashes')) {
  /**
   * Prints one-time status messages for the current session, if any. Static pages
   * show them too, so a message survives a redirect to the home page.
   */
  function uvs_render_flashes(): void
  {
    $flashes = [];
    try {
      if (class_exists(\Uvs\App::class, false)) {
        $session = \Uvs\App::instance()->session();
        if ($session->isActive()) {
          $flashes = $session->pullFlashes();
        }
      }
    } catch (\Throwable) {
      $flashes = [];
    }
    if ($flashes === []) {
      return;
    }
    echo '<div class="flash-stack" role="status">';
    foreach ($flashes as $flash) {
      $type = in_array($flash['type'] ?? '', ['success', 'info', 'warning', 'error'], true) ? $flash['type'] : 'info';
      echo '<p class="notice notice-' . h($type) . '">' . h((string) ($flash['message'] ?? '')) . '</p>';
    }
    echo '</div>';
  }
}
