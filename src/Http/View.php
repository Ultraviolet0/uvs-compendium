<?php

declare(strict_types=1);

namespace Uvs\Http;

use RuntimeException;
use Uvs\App;
use Uvs\Security\Csrf;
use Uvs\Support\Text;

/**
 * Renders a PHP template inside the shared site header and footer. Templates
 * escape all dynamic output with h(); this class only provides helpers.
 */
final class View
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $old = [];

    public function __construct(private readonly App $app, private readonly Request $request)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array{title?: string, description?: string, current?: string, styles?: list<string>, scripts?: list<string>, robots?: string, layout?: bool} $page
     */
    public function render(string $template, array $data = [], array $page = []): string
    {
        $this->errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
        $this->old = is_array($data['old'] ?? null) ? $data['old'] : [];
        $file = $this->app->root . '/templates/' . $template . '.php';
        if (!preg_match('#^[a-z0-9/_-]+$#', $template) || !is_file($file)) {
            throw new RuntimeException('Unknown template.');
        }

        $GLOBALS['page_title'] = ($page['title'] ?? "UV's Compendium") . " | UV's Compendium";
        $GLOBALS['page_description'] = $page['description'] ?? "UV's Compendium community pages.";
        $GLOBALS['base_path'] = $this->request->basePath();
        $GLOBALS['current_page'] = $page['current'] ?? 'app';
        $GLOBALS['page_styles'] = array_merge(['css/app.css'], $page['styles'] ?? []);
        $GLOBALS['page_scripts'] = array_merge(['js/app.js'], $page['scripts'] ?? []);
        $GLOBALS['page_robots'] = $page['robots'] ?? 'noindex';

        $view = $this;
        $render = static function (string $__file, array $__data) use ($view): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };
        ob_start();
        try {
            if (($page['layout'] ?? true) === true) {
                global $page_title, $page_description, $base_path, $current_page, $page_styles, $page_scripts, $page_robots;
                require $this->app->root . '/includes/public_header.php';
                $render($file, $data);
                require $this->app->root . '/includes/public_footer.php';
            } else {
                $render($file, $data);
            }
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }

    public function partial(string $name, array $data = []): void
    {
        $file = $this->app->root . '/templates/partials/' . $name . '.php';
        if (!preg_match('#^[a-z0-9_-]+$#', $name) || !is_file($file)) {
            throw new RuntimeException('Unknown partial.');
        }
        $view = $this;
        (static function (string $__file, array $__data) use ($view): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        })($file, $data);
    }

    public function url(string $path = ''): string
    {
        return site_url($path);
    }

    public function csrf(): string
    {
        return (new Csrf($this->app->session()))->field();
    }

    public function csrfToken(): string
    {
        return (new Csrf($this->app->session()))->token();
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function old(string $field, string $default = ''): string
    {
        $value = $this->old[$field] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    /** Attributes linking an input to its error message and marking it invalid. */
    public function invalid(string $field, string $describedBy = ''): string
    {
        $ids = trim($describedBy . ($this->error($field) !== null ? ' ' . $field . '-error' : ''));
        $attributes = $ids !== '' ? ' aria-describedby="' . h($ids) . '"' : '';
        return $attributes . ($this->error($field) !== null ? ' aria-invalid="true"' : '');
    }

    public function fieldError(string $field): string
    {
        $message = $this->error($field);
        return $message === null ? '' : '<p class="field-error" id="' . h($field) . '-error">' . h($message) . '</p>';
    }

    public function date(?string $value, string $format = 'F j, Y'): string
    {
        return h(Text::date($value, $format));
    }

    public function time(?string $value, string $format = 'F j, Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return '<time datetime="' . h(Text::isoDate($value)) . '">' . h(Text::date($value, $format)) . '</time>';
    }

    public function user(): ?array
    {
        return $this->app->auth()->user();
    }

    public function can(string $ability, ?array $subject = null): bool
    {
        return $this->app->gate()->allows($this->user(), $ability, $subject);
    }

    public function app(): App
    {
        return $this->app;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function mediaUrl(string $publicId, string $extension): string
    {
        return $this->url('media/' . $publicId . '.' . $extension);
    }
}
