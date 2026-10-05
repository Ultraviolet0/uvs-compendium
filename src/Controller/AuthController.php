<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Auth\Mfa;
use Uvs\Auth\PasswordPolicy;
use Uvs\Http\Response;
use Uvs\Security\FormTimer;
use Uvs\Security\Turnstile\Turnstile;
use Uvs\Support\Text;
use Uvs\Support\ValidationException;
use Uvs\Users\AccountService;
use Uvs\Users\PasswordResetService;

final class AuthController extends Controller
{
    private const HONEYPOT = 'website';

    public function signupForm(): Response
    {
        if ($this->user() !== null) {
            return $this->redirect('account/');
        }
        return $this->signupPage();
    }

    public function signup(): Response
    {
        if ($this->user() !== null) {
            return $this->redirect('account/');
        }
        $closed = $this->signupBlocked();
        if ($closed !== null) {
            return $this->signupPage();
        }
        $old = [
            'username' => Text::line($this->request->input('username'), 40),
            'email' => Text::line($this->request->input('email'), 254),
        ];
        if (!$this->app->rateLimiter()->hit('signup.ip', $this->ip())) {
            return $this->signupPage(['form' => 'Too many signup attempts from your network. Please try again later.'], $old, 429);
        }
        if (trim($this->request->input(self::HONEYPOT)) !== '') {
            $this->app->logger()->info('Signup rejected by honeypot');
            return $this->signupPage(['form' => 'We could not process this signup. Please try again.'], $old, 422);
        }
        $timing = $this->formTimer()->check('signup', $this->request->input(FormTimer::FIELD),
            (int) $this->app->config->get('signup.min_seconds', 3), (int) $this->app->config->get('signup.max_age', 7200));
        if ($timing !== 'ok') {
            $message = $timing === 'too_fast'
                ? 'That was quicker than expected. Please check the form and submit it again.'
                : 'This form expired. Please submit it again.';
            return $this->signupPage(['form' => $message], $old, 422);
        }
        $turnstile = $this->app->turnstile()->verify($this->request->input(Turnstile::RESPONSE_FIELD), $this->ip(), 'signup');
        if (!$turnstile->success) {
            return $this->signupPage(['turnstile' => 'The anti-bot check did not pass. Please complete it and try again.'], $old, 422);
        }
        $password = $this->request->input('password');
        if (!hash_equals($password, $this->request->input('password_confirmation'))) {
            return $this->signupPage(['password_confirmation' => 'The passwords do not match.'], $old, 422);
        }
        if ($this->request->input('accept_privacy') !== '1') {
            return $this->signupPage(['accept_privacy' => 'Please confirm you have read the privacy notice.'], $old, 422);
        }
        try {
            $result = $this->accounts()->register($old['username'], $old['email'], $password);
        } catch (ValidationException $error) {
            return $this->signupPage($error->errors, $old, 422);
        }
        $user = $this->app->users()->find($result['id']);
        if ($user !== null) {
            $this->app->auth()->completeLogin($user);
        }
        $this->flash('success', $result['status'] === 'active'
            ? 'Welcome to UV\'s Compendium! Your account is active.'
            : 'Welcome! Your account is awaiting approval. You can set up your profile while you wait.');
        return $this->redirect('account/');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function signupPage(array $errors = [], array $old = [], int $status = 200): Response
    {
        $turnstile = $this->app->turnstile();
        return $this->render('auth/signup', [
            'errors' => $errors,
            'old' => $old,
            'closed' => $this->signupBlocked(),
            'timer' => $this->formTimer()->issue('signup'),
            'turnstile' => $turnstile,
            'policy' => $this->policy(),
            'honeypot' => self::HONEYPOT,
            'autoApprove' => $this->app->settings()->bool('auto_approve_accounts'),
        ], [
            'title' => 'Create an account',
            'description' => "Join UV's Compendium to publish Diablo I and Hellfire guides.",
            'current' => 'signup',
            'scripts' => $turnstile->status() === 'enabled' ? ['js/turnstile.js'] : [],
            'robots' => 'index',
        ], $status);
    }

    private function signupBlocked(): ?string
    {
        if (!$this->app->settings()->bool('registrations_enabled')) {
            return 'Registration is currently closed. Please check back later.';
        }
        if (!$this->app->turnstile()->isAvailable()) {
            return 'Registration is temporarily unavailable while anti-bot protection is being configured.';
        }
        return null;
    }

    public function loginForm(): Response
    {
        if ($this->user() !== null) {
            return $this->redirect('account/');
        }
        return $this->loginPage();
    }

    public function login(): Response
    {
        $identifier = Text::line($this->request->input('identifier'), 254);
        $next = $this->safeNext($this->request->input('next'));
        $attempt = $this->app->auth()->attempt($identifier, $this->request->input('password'), $this->ip());
        switch ($attempt['result']) {
            case 'throttled':
                return $this->loginPage(['form' => 'Too many sign-in attempts. Please wait 15 minutes and try again.'], $identifier, 429);
            case 'invalid':
                return $this->loginPage(['form' => 'That username or email and password combination is not correct.'], $identifier, 422);
            case 'suspended':
                $reason = (string) ($attempt['user']['status_reason'] ?? '');
                return $this->loginPage(['form' => 'This account is suspended.' . ($reason !== '' ? ' Reason: ' . $reason : '')], $identifier, 403);
            case 'rejected':
                return $this->loginPage(['form' => 'This account application was not approved.'], $identifier, 403);
            case 'mfa':
                $this->app->auth()->beginMfa($attempt['user']);
                return $this->redirect('account/login/verify/' . ($next !== null ? '?next=' . rawurlencode($next) : ''));
        }
        $this->app->auth()->completeLogin($attempt['user']);
        $this->flash('success', 'Signed in as ' . $attempt['user']['username'] . '.');
        return $this->redirect($next ?? 'account/');
    }

    /**
     * @param array<string, string> $errors
     */
    private function loginPage(array $errors = [], string $identifier = '', int $status = 200): Response
    {
        return $this->render('auth/login', [
            'errors' => $errors,
            'old' => ['identifier' => $identifier],
            'next' => $this->safeNext($this->request->input('next') ?: $this->request->query('next')),
            'mailEnabled' => $this->app->mailer()->isEnabled(),
        ], ['title' => 'Sign in', 'current' => 'login'], $status);
    }

    public function mfaForm(): Response
    {
        if ($this->app->auth()->pendingMfaUser() === null) {
            return $this->redirect('account/login/');
        }
        return $this->render('auth/mfa', ['errors' => [], 'next' => $this->safeNext($this->request->query('next'))],
            ['title' => 'Two-factor verification', 'current' => 'login']);
    }

    public function mfa(): Response
    {
        $user = $this->app->auth()->pendingMfaUser();
        if ($user === null) {
            $this->flash('info', 'Your sign-in attempt expired. Please sign in again.');
            return $this->redirect('account/login/');
        }
        $next = $this->safeNext($this->request->input('next'));
        if (!$this->app->rateLimiter()->hit('mfa.user', (string) $user['id'])) {
            return $this->render('auth/mfa', ['errors' => ['code' => 'Too many attempts. Please wait 15 minutes.'], 'next' => $next],
                ['title' => 'Two-factor verification'], 429);
        }
        $method = $this->mfaService()->verify($user, Text::line($this->request->input('code'), 40));
        if ($method === null) {
            return $this->render('auth/mfa', ['errors' => ['code' => 'That code is not valid. Check your authenticator app and try again.'], 'next' => $next],
                ['title' => 'Two-factor verification'], 422);
        }
        $this->app->rateLimiter()->clear('mfa.user', (string) $user['id']);
        $this->app->auth()->completeLogin($user);
        if ($method === 'recovery') {
            $left = $this->mfaService()->remainingRecoveryCodes((int) $user['id']);
            $this->flash('warning', "You used a recovery code. {$left} remain; generate new codes from Account security if you are running low.");
        } else {
            $this->flash('success', 'Signed in as ' . $user['username'] . '.');
        }
        return $this->redirect($next ?? 'account/');
    }

    public function logout(): Response
    {
        $this->app->auth()->logout();
        $this->flash('info', 'You have signed out.');
        return Response::redirect($this->request->url(''));
    }

    public function forgotForm(): Response
    {
        return $this->forgotPage();
    }

    public function forgot(): Response
    {
        $email = Text::line($this->request->input('email'), 254);
        if (!$this->app->turnstile()->isAvailable()) {
            return $this->forgotPage();
        }
        if (!$this->app->rateLimiter()->hit('reset.ip', $this->ip())) {
            return $this->forgotPage(['form' => 'Too many reset requests from your network. Please try again later.'], $email, 429);
        }
        $verification = $this->app->turnstile()->verify($this->request->input(Turnstile::RESPONSE_FIELD), $this->ip(), 'password_reset');
        if (!$verification->success) {
            return $this->forgotPage(['turnstile' => 'The anti-bot check did not pass. Please try again.'], $email, 422);
        }
        // Per-address throttling is silent so it reveals nothing about the address.
        if ($this->app->rateLimiter()->hit('reset.account', $email)) {
            $this->resets()->request($email, $this->absoluteUrl('account/password/reset/'));
        }
        $this->flash('info', 'If that address belongs to an eligible account, a reset link is on its way. It expires in one hour.');
        return $this->redirect('account/login/');
    }

    /**
     * @param array<string, string> $errors
     */
    private function forgotPage(array $errors = [], string $email = '', int $status = 200): Response
    {
        $turnstile = $this->app->turnstile();
        return $this->render('auth/forgot', [
            'errors' => $errors,
            'old' => ['email' => $email],
            'turnstile' => $turnstile,
            'mailEnabled' => $this->app->mailer()->isEnabled(),
        ], ['title' => 'Reset your password', 'current' => 'login',
            'scripts' => $turnstile->status() === 'enabled' ? ['js/turnstile.js'] : []], $status);
    }

    public function resetForm(): Response
    {
        $token = $this->request->query('token');
        if ($token !== '') {
            // Move the token out of the URL so it cannot leak through history or referrers.
            $this->app->session()->set('reset_token', mb_substr($token, 0, 100));
            return $this->redirect('account/password/reset/');
        }
        $stored = $this->app->session()->get('reset_token');
        $valid = is_string($stored) && $this->resets()->userForToken($stored) !== null;
        return $this->render('auth/reset', ['errors' => [], 'valid' => $valid, 'policy' => $this->policy()],
            ['title' => 'Choose a new password', 'current' => 'login']);
    }

    public function reset(): Response
    {
        $stored = $this->app->session()->get('reset_token');
        try {
            $this->resets()->complete(is_string($stored) ? $stored : '', $this->request->input('password'),
                $this->request->input('password_confirmation'));
        } catch (ValidationException $error) {
            $valid = !isset($error->errors['token']);
            return $this->render('auth/reset', ['errors' => $error->errors, 'valid' => $valid, 'policy' => $this->policy()],
                ['title' => 'Choose a new password'], 422);
        }
        $this->app->session()->remove('reset_token');
        $this->app->auth()->logout();
        $this->flash('success', 'Your password has been changed and other sessions were signed out. Please sign in.');
        return $this->redirect('account/login/');
    }

    private function safeNext(string $next): ?string
    {
        if ($next === '' || !preg_match('#^/(?!/)[A-Za-z0-9/_.\-]*$#', $next) || str_contains($next, '..')
            || str_starts_with($next, '/account/login') || str_starts_with($next, '/account/logout')) {
            return null;
        }
        return ltrim($next, '/');
    }

    private function absoluteUrl(string $path): string
    {
        $base = $this->app->config->get('base_url');
        if (!is_string($base) || $base === '') {
            // Development and test only: production configuration requires base_url.
            $host = (string) $this->request->header('Host');
            $base = preg_match('/^(?:localhost|127\.0\.0\.1)(?::\d{1,5})?$/', $host) ? 'http://' . $host : 'http://localhost';
            $base .= $this->request->basePrefix;
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private function formTimer(): FormTimer
    {
        return new FormTimer($this->app->derivedKey('form-timer'));
    }

    private function policy(): PasswordPolicy
    {
        return $this->app->passwordPolicy();
    }

    private function accounts(): AccountService
    {
        return $this->app->accounts();
    }

    private function resets(): PasswordResetService
    {
        return $this->app->passwordResets();
    }

    private function mfaService(): Mfa
    {
        return $this->app->mfa();
    }
}
