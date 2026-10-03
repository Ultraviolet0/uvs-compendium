<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\App;
use Uvs\Http\HttpException;
use Uvs\Http\Request;
use Uvs\Http\Response;
use Uvs\Http\View;

abstract class Controller
{
    protected readonly View $view;

    public function __construct(protected readonly App $app, protected readonly Request $request)
    {
        $this->view = new View($app, $request);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $page
     */
    protected function render(string $template, array $data = [], array $page = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data, $page), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($this->request->url($path));
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session()->flash($type, $message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function user(): ?array
    {
        return $this->app->auth()->user();
    }

    /**
     * Requires a signed-in (pending or active) account.
     *
     * @return array<string, mixed>
     */
    protected function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new HttpException(401, 'Sign in required');
        }
        return $user;
    }

    /**
     * @param array<string, mixed>|null $subject
     * @return array<string, mixed>
     */
    protected function authorize(string $ability, ?array $subject = null, ?string $detail = null): array
    {
        $user = $this->requireUser();
        if (!$this->app->gate()->allows($user, $ability, $subject)) {
            throw HttpException::forbidden($detail ?? 'Your account does not have permission to do that.');
        }
        return $user;
    }

    protected function ip(): string
    {
        return $this->request->ip($this->app->config);
    }

    protected function tooManyRequests(string $message = 'Too many attempts. Please wait a few minutes and try again.'): HttpException
    {
        return new HttpException(429, 'Too many requests', $message);
    }
}
