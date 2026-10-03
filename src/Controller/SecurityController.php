<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Auth\Mfa;
use Uvs\Auth\PasswordHasher;
use Uvs\Database;
use Uvs\Http\Response;
use Uvs\Support\Text;
use Uvs\Users\EmailAddress;

final class SecurityController extends Controller
{
    private const SETUP_KEY = 'mfa_setup_secret';

    public function show(): Response
    {
        $user = $this->authorize('account.manage');
        return $this->page($user);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     */
    private function page(array $user, array $errors = [], int $status = 200): Response
    {
        return $this->render('account/security', [
            'user' => $user,
            'errors' => $errors,
            'policy' => $this->app->passwordPolicy(),
            'recoveryLeft' => $user['mfa_enabled_at'] !== null ? $this->app->mfa()->remainingRecoveryCodes((int) $user['id']) : 0,
        ], ['title' => 'Account security', 'current' => 'account'], $status);
    }

    public function changePassword(): Response
    {
        $user = $this->authorize('account.manage');
        if (!$this->app->rateLimiter()->hit('password.change', (string) $user['id'])) {
            return $this->page($user, ['current_password' => 'Too many attempts. Please wait 15 minutes.'], 429);
        }
        if (!PasswordHasher::verify($this->request->input('current_password'), (string) $user['password_hash'])) {
            return $this->page($user, ['current_password' => 'Your current password is not correct.'], 422);
        }
        $password = $this->request->input('new_password');
        if (!hash_equals($password, $this->request->input('new_password_confirmation'))) {
            return $this->page($user, ['new_password_confirmation' => 'The new passwords do not match.'], 422);
        }
        $problems = $this->app->passwordPolicy()->validate($password, (string) $user['username'], (string) $user['email']);
        if ($problems !== []) {
            return $this->page($user, ['new_password' => $problems[0]], 422);
        }
        $this->app->users()->updatePasswordHash((int) $user['id'], PasswordHasher::hash($password), true);
        $this->app->auth()->rebindCurrentSession();
        $this->flash('success', 'Password changed. Any other signed-in sessions have been signed out.');
        return $this->redirect('account/security/');
    }

    public function changeEmail(): Response
    {
        $user = $this->authorize('account.manage');
        if (!$this->app->rateLimiter()->hit('password.change', (string) $user['id'])) {
            return $this->page($user, ['email_password' => 'Too many attempts. Please wait 15 minutes.'], 429);
        }
        if (!PasswordHasher::verify($this->request->input('email_password'), (string) $user['password_hash'])) {
            return $this->page($user, ['email_password' => 'Your password is not correct.'], 422);
        }
        $email = Text::line($this->request->input('email'), 254);
        if (!EmailAddress::isValid($email)) {
            return $this->page($user, ['email' => 'Enter a valid email address.'], 422);
        }
        if ($this->app->users()->emailTaken($email, (int) $user['id'])) {
            return $this->page($user, ['email' => 'That email address cannot be used.'], 422);
        }
        $old = (string) $user['email'];
        $this->app->db()->execute('UPDATE users SET email = :email, email_key = :key, updated_at = :now WHERE id = :id',
            ['email' => $email, 'key' => EmailAddress::normalize($email), 'now' => Database::now(), 'id' => (int) $user['id']]);
        if (EmailAddress::normalize($old) !== EmailAddress::normalize($email)) {
            $this->app->mailer()->send($old, "Your UV's Compendium email address changed",
                "Hello {$user['username']},\n\nThe email address on your UV's Compendium account was just changed. "
                . "If you did not do this, reset your password and contact the site administrator.\n");
        }
        $this->flash('success', 'Email address updated.');
        return $this->redirect('account/security/');
    }

    public function mfaSetup(): Response
    {
        $user = $this->authorize('account.manage');
        if ($user['mfa_enabled_at'] !== null) {
            return $this->redirect('account/security/');
        }
        return $this->setupPage($user);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     */
    private function setupPage(array $user, array $errors = [], int $status = 200): Response
    {
        $secret = $this->app->session()->get(self::SETUP_KEY);
        if (!is_string($secret) || !preg_match('/^[A-Z2-7]{32}$/', $secret)) {
            $secret = Mfa::newSecret();
            $this->app->session()->set(self::SETUP_KEY, $secret);
        }
        $uri = Mfa::provisioningUri($secret, (string) $user['username']);
        return $this->render('account/mfa-setup', [
            'errors' => $errors,
            'secret' => $secret,
            'qr' => Mfa::qrSvg($uri),
        ], ['title' => 'Set up two-factor authentication', 'current' => 'account'], $status);
    }

    public function mfaEnable(): Response
    {
        $user = $this->authorize('account.manage');
        if ($user['mfa_enabled_at'] !== null) {
            return $this->redirect('account/security/');
        }
        $secret = $this->app->session()->get(self::SETUP_KEY);
        if (!is_string($secret)) {
            return $this->redirect('account/security/two-factor/');
        }
        if (!$this->app->rateLimiter()->hit('password.change', (string) $user['id'])) {
            return $this->setupPage($user, ['code' => 'Too many attempts. Please wait 15 minutes.'], 429);
        }
        if (!PasswordHasher::verify($this->request->input('password'), (string) $user['password_hash'])) {
            return $this->setupPage($user, ['password' => 'Your password is not correct.'], 422);
        }
        $step = Mfa::matchStep($secret, $this->request->input('code'), null);
        if ($step === null) {
            return $this->setupPage($user, ['code' => 'That code did not match. Check the time on your device and try again.'], 422);
        }
        $codes = $this->app->mfa()->enable((int) $user['id'], $secret, $step);
        $this->app->session()->remove(self::SETUP_KEY);
        $this->app->auth()->rebindCurrentSession();
        return $this->codesPage($codes, true);
    }

    public function mfaDisable(): Response
    {
        $user = $this->authorize('account.manage');
        if ($user['mfa_enabled_at'] === null) {
            return $this->redirect('account/security/');
        }
        if (!$this->app->rateLimiter()->hit('password.change', (string) $user['id'])) {
            return $this->page($user, ['disable_code' => 'Too many attempts. Please wait 15 minutes.'], 429);
        }
        if (!PasswordHasher::verify($this->request->input('disable_password'), (string) $user['password_hash'])) {
            return $this->page($user, ['disable_password' => 'Your password is not correct.'], 422);
        }
        if ($this->app->mfa()->verify($user, $this->request->input('disable_code')) === null) {
            return $this->page($user, ['disable_code' => 'That code is not valid.'], 422);
        }
        $this->app->mfa()->disable((int) $user['id']);
        $this->app->auth()->rebindCurrentSession();
        $this->flash('warning', 'Two-factor authentication is now off.');
        return $this->redirect('account/security/');
    }

    public function regenerateCodes(): Response
    {
        $user = $this->authorize('account.manage');
        if ($user['mfa_enabled_at'] === null) {
            return $this->redirect('account/security/');
        }
        if (!PasswordHasher::verify($this->request->input('codes_password'), (string) $user['password_hash'])) {
            return $this->page($user, ['codes_password' => 'Your password is not correct.'], 422);
        }
        return $this->codesPage($this->app->mfa()->replaceRecoveryCodes((int) $user['id']), false);
    }

    /**
     * @param list<string> $codes
     */
    private function codesPage(array $codes, bool $justEnabled): Response
    {
        return $this->render('account/recovery-codes', ['codes' => $codes, 'justEnabled' => $justEnabled],
            ['title' => 'Recovery codes', 'current' => 'account']);
    }
}
