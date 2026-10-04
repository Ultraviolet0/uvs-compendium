<?php

declare(strict_types=1);

namespace Uvs\Http;

use PDOException;
use Throwable;
use Uvs\App;
use Uvs\Controller\AccountController;
use Uvs\Controller\Admin\AuditController;
use Uvs\Controller\Admin\DashboardController;
use Uvs\Controller\Admin\GuideAdminController;
use Uvs\Controller\Admin\SettingsController;
use Uvs\Controller\Admin\UserAdminController;
use Uvs\Controller\AuthController;
use Uvs\Controller\CharacterController;
use Uvs\Controller\GuideController;
use Uvs\Controller\GuideEditorController;
use Uvs\Controller\MediaController;
use Uvs\Controller\MemberController;
use Uvs\Controller\ProfileController;
use Uvs\Controller\SecurityController;
use Uvs\Security\Csrf;

/**
 * Front controller: routing, CSRF and origin enforcement for every POST, and
 * friendly error pages that never expose internal details.
 */
final class Kernel
{
    private const ID = '[1-9][0-9]{0,9}';

    public function __construct(private readonly App $app)
    {
    }

    public static function routes(): Router
    {
        $r = new Router();
        $id = self::ID;

        $r->get('/account/signup/', [AuthController::class, 'signupForm']);
        $r->post('/account/signup/', [AuthController::class, 'signup']);
        $r->get('/account/login/', [AuthController::class, 'loginForm']);
        $r->post('/account/login/', [AuthController::class, 'login']);
        $r->get('/account/login/verify/', [AuthController::class, 'mfaForm']);
        $r->post('/account/login/verify/', [AuthController::class, 'mfa']);
        $r->post('/account/logout/', [AuthController::class, 'logout']);
        $r->get('/account/password/forgot/', [AuthController::class, 'forgotForm']);
        $r->post('/account/password/forgot/', [AuthController::class, 'forgot']);
        $r->get('/account/password/reset/', [AuthController::class, 'resetForm']);
        $r->post('/account/password/reset/', [AuthController::class, 'reset']);

        $r->get('/account/', [AccountController::class, 'dashboard']);
        $r->post('/account/theme/', [AccountController::class, 'theme']);

        $r->get('/account/profile/', [ProfileController::class, 'edit']);
        $r->post('/account/profile/', [ProfileController::class, 'update']);
        $r->post('/account/profile/avatar/', [ProfileController::class, 'uploadAvatar']);
        $r->post('/account/profile/avatar/delete/', [ProfileController::class, 'deleteAvatar']);

        $r->get('/account/security/', [SecurityController::class, 'show']);
        $r->post('/account/security/password/', [SecurityController::class, 'changePassword']);
        $r->post('/account/security/email/', [SecurityController::class, 'changeEmail']);
        $r->get('/account/security/two-factor/', [SecurityController::class, 'mfaSetup']);
        $r->post('/account/security/two-factor/', [SecurityController::class, 'mfaEnable']);
        $r->post('/account/security/two-factor/disable/', [SecurityController::class, 'mfaDisable']);
        $r->post('/account/security/two-factor/recovery-codes/', [SecurityController::class, 'regenerateCodes']);

        $r->get('/account/characters/', [CharacterController::class, 'index']);
        $r->post('/account/characters/', [CharacterController::class, 'create']);
        $r->get("/account/characters/{id:{$id}}/edit/", [CharacterController::class, 'edit']);
        $r->post("/account/characters/{id:{$id}}/", [CharacterController::class, 'update']);
        $r->post("/account/characters/{id:{$id}}/delete/", [CharacterController::class, 'delete']);
        $r->post("/account/characters/{id:{$id}}/move/", [CharacterController::class, 'move']);

        $r->get('/account/guides/', [GuideEditorController::class, 'index']);
        $r->get('/account/guides/new/', [GuideEditorController::class, 'createForm']);
        $r->post('/account/guides/new/', [GuideEditorController::class, 'create']);
        $r->post('/account/guides/preview/', [GuideEditorController::class, 'renderPreview']);
        $r->get("/account/guides/{id:{$id}}/edit/", [GuideEditorController::class, 'edit']);
        $r->post("/account/guides/{id:{$id}}/save/", [GuideEditorController::class, 'save']);
        $r->post("/account/guides/{id:{$id}}/submit/", [GuideEditorController::class, 'submit']);
        $r->post("/account/guides/{id:{$id}}/withdraw/", [GuideEditorController::class, 'withdraw']);
        $r->post("/account/guides/{id:{$id}}/delete/", [GuideEditorController::class, 'delete']);
        $r->get("/account/guides/{id:{$id}}/preview/", [GuideEditorController::class, 'preview']);
        $r->post("/account/guides/{id:{$id}}/media/", [GuideEditorController::class, 'uploadMedia']);
        $r->post('/account/media/{public_id:[A-Za-z0-9_-]{22}}/delete/', [GuideEditorController::class, 'deleteMedia']);

        $r->get('/members/', [MemberController::class, 'directory']);
        $r->get('/members/{username:[A-Za-z0-9_-]{1,40}}/', [MemberController::class, 'profile']);
        $r->get('/guides/{slug:[a-z0-9]+(?:-[a-z0-9]+)*}/', [GuideController::class, 'show']);
        $r->get('/media/{public_id:[A-Za-z0-9_-]{22}}.{ext:webp|jpg|png}', [MediaController::class, 'show']);

        $r->get('/admin/', [DashboardController::class, 'index']);
        $r->get('/admin/users/', [UserAdminController::class, 'index']);
        $r->get("/admin/users/{id:{$id}}/", [UserAdminController::class, 'show']);
        $r->post("/admin/users/{id:{$id}}/status/", [UserAdminController::class, 'status']);
        $r->post("/admin/users/{id:{$id}}/role/", [UserAdminController::class, 'role']);
        $r->post("/admin/users/{id:{$id}}/note/", [UserAdminController::class, 'note']);
        $r->get('/admin/submissions/', [GuideAdminController::class, 'submissions']);
        $r->get('/admin/guides/', [GuideAdminController::class, 'index']);
        $r->get("/admin/guides/{id:{$id}}/", [GuideAdminController::class, 'show']);
        $r->get("/admin/guides/{id:{$id}}/edit/", [GuideAdminController::class, 'edit']);
        $r->post("/admin/guides/{id:{$id}}/edit/", [GuideAdminController::class, 'update']);
        $r->post("/admin/guides/{id:{$id}}/action/", [GuideAdminController::class, 'action']);
        $r->get("/admin/guides/{id:{$id}}/revisions/{revision:{$id}}/", [GuideAdminController::class, 'revision']);
        $r->get('/admin/settings/', [SettingsController::class, 'show']);
        $r->post('/admin/settings/', [SettingsController::class, 'update']);
        $r->get('/admin/audit/', [AuditController::class, 'index']);

        return $r;
    }

    public function handle(Request $request): Response
    {
        try {
            if (!$this->app->config->isUsable()) {
                $this->app->config->isProduction() && $this->app->logger()->warning('Community application unconfigured',
                    ['problems' => $this->app->config->problems()]);
                return $this->unavailable();
            }
            $router = self::routes();
            [$handler, $params, $allowed] = $router->match($request->method, $request->path);
            if ($handler === null) {
                if ($request->method === 'GET' && $router->hasCanonicalSlash($request->path)) {
                    $query = (string) parse_url((string) $request->server('REQUEST_URI'), PHP_URL_QUERY);
                    return Response::redirect($request->url($request->path . '/') . ($query !== '' ? '?' . $query : ''), 301);
                }
                if ($allowed !== []) {
                    return $this->error($request, new HttpException(405, 'Method not allowed'))
                        ->withHeader('Allow', implode(', ', $allowed));
                }
                throw HttpException::notFound();
            }
            if ($request->method === 'POST') {
                $this->verifyPost($request);
            }
            [$class, $method] = $handler;
            $response = (new $class($this->app, $request))->{$method}(...array_values($params));
            return $response;
        } catch (HttpException $error) {
            if ($error->status === 401) {
                return $this->signInRequired($request);
            }
            return $this->error($request, $error);
        } catch (PDOException $error) {
            $this->app->logger()->exception($error, 'Database unavailable or query failed');
            return $this->unavailable($request);
        } catch (Throwable $error) {
            $this->app->logger()->exception($error);
            return $this->error($request, new HttpException(500, 'Something went wrong'));
        }
    }

    private function verifyPost(Request $request): void
    {
        // Reject cross-site form posts outright when the browser reports an origin.
        $origin = $request->header('Origin');
        $host = $request->header('Host');
        if ($origin !== null && $origin !== 'null' && $host !== null
            && strcasecmp((string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : ''), $host) !== 0) {
            throw new HttpException(403, 'Request blocked', 'This form was submitted from another site.');
        }
        $session = $this->app->session();
        $session->resumeIfPresent();
        $token = $request->input(Csrf::FIELD) ?: $request->header('X-CSRF-Token');
        if (!(new Csrf($session))->isValid($token)) {
            throw new HttpException(403, 'Form expired',
                'Your form expired or could not be verified. Go back, reload the page, and try again.');
        }
    }

    private function signInRequired(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => 'Sign in required.'], 401);
        }
        $next = $request->method === 'GET' ? '?next=' . rawurlencode($request->path) : '';
        $this->app->session()->flash('info', 'Please sign in to continue.');
        return Response::redirect($request->url('account/login/') . $next);
    }

    private function error(Request $request, HttpException $error): Response
    {
        $titles = [
            403 => 'Access denied', 404 => 'Page not found', 405 => 'Method not allowed',
            413 => 'Upload too large', 429 => 'Slow down', 500 => 'Something went wrong',
        ];
        $title = $titles[$error->status] ?? 'Request failed';
        $detail = $error->status === 500 ? null : $error->detail;
        if ($request->wantsJson()) {
            return Response::json(['error' => $detail ?? $title], $error->status);
        }
        try {
            $html = (new View($this->app, $request))->render('errors/error', [
                'status' => $error->status, 'title' => $title, 'detail' => $detail,
            ], ['title' => $title]);
            return Response::html($html, $error->status);
        } catch (Throwable $renderError) {
            $this->app->logger()->exception($renderError, 'Error page rendering failed');
            return Response::html(self::plainPage($title, 'Please return to the home page.'), $error->status);
        }
    }

    private function unavailable(?Request $request = null): Response
    {
        if ($request !== null && $request->wantsJson()) {
            return Response::json(['error' => 'Community features are temporarily unavailable.'], 503);
        }
        return Response::html(self::plainPage('Temporarily unavailable',
            'Community features are temporarily unavailable. The calculators and guides still work normally.'), 503)
            ->withHeader('Retry-After', '300');
    }

    /** Self-contained page used when the application itself cannot render. */
    public static function plainPage(string $title, string $message): string
    {
        return '<!doctype html><html lang="en" data-theme="dark"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . h($title) . " | UV's Compendium</title>"
            . '<link rel="stylesheet" href="/css/styles.css"></head><body><main id="main-content" class="site-main plain-main">'
            . '<section class="section-panel flow"><p class="eyebrow">UV\'s Compendium</p><h1>' . h($title) . '</h1><p>' . h($message)
            . '</p><p><a class="button button-secondary" href="/">Return home</a></p></section></main></body></html>';
    }
}
