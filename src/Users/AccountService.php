<?php

declare(strict_types=1);

namespace Uvs\Users;

use PDOException;
use Uvs\Admin\AuditLog;
use Uvs\Admin\Settings;
use Uvs\Auth\PasswordHasher;
use Uvs\Auth\PasswordPolicy;
use Uvs\Database;
use Uvs\Support\ValidationException;

/**
 * Account creation and administrative status/role changes. All account-state
 * transitions go through here so the rules are enforced in one place.
 */
final class AccountService
{
    /** action => [allowed source statuses, target status, audit action] */
    public const STATUS_ACTIONS = [
        'approve' => [['pending', 'rejected'], 'active', 'user.approved'],
        'reject' => [['pending'], 'rejected', 'user.rejected'],
        'suspend' => [['pending', 'active'], 'suspended', 'user.suspended'],
        'reactivate' => [['suspended', 'rejected'], 'active', 'user.reactivated'],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly Settings $settings,
        private readonly AuditLog $audit,
        private readonly PasswordPolicy $passwords,
    ) {
    }

    /**
     * Registers a member. New accounts are pending unless automatic approval is on.
     *
     * @return array{id: int, status: string}
     * @throws ValidationException
     */
    public function register(string $username, string $email, string $password): array
    {
        $username = trim($username);
        $email = trim($email);
        $errors = [];
        foreach (UsernamePolicy::validate($username) as $message) {
            $errors['username'] = $message;
        }
        if (!EmailAddress::isValid($email)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        foreach ($this->passwords->validate($password, $username, $email) as $message) {
            $errors['password'] = $message;
        }
        if (!isset($errors['username']) && $this->users->usernameTaken($username)) {
            $errors['username'] = 'That username is already taken.';
        }
        if (!isset($errors['email']) && $this->users->emailTaken($email)) {
            $errors['email'] = 'That email address cannot be used for a new account. If it is yours, sign in or reset your password.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $status = $this->settings->bool('auto_approve_accounts') ? 'active' : 'pending';
        try {
            $id = $this->db->transaction(fn () => $this->users->create($username, $email, PasswordHasher::hash($password), $status));
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                // A concurrent signup claimed the name or address between validation and insert.
                throw new ValidationException(['username' => 'That username or email address is no longer available.']);
            }
            throw $error;
        }
        $this->audit->record(['id' => $id, 'username' => $username], 'user.registered', 'user', $id, $username,
            ['status' => $status]);
        return ['id' => $id, 'status' => $status];
    }

    /**
     * Creates the first administrator from the command line. Refuses if any
     * administrator already exists.
     *
     * @throws ValidationException
     */
    public function bootstrapAdmin(string $username, string $email, string $password): int
    {
        if ($this->users->countAdmins(false) > 0) {
            throw new ValidationException(['admin' => 'An administrator already exists. Bootstrap is disabled.']);
        }
        $errors = [];
        foreach (UsernamePolicy::validate($username, allowReserved: true) as $message) {
            $errors['username'] = $message;
        }
        if (!EmailAddress::isValid($email)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        foreach ($this->passwords->validate($password, $username, $email) as $message) {
            $errors['password'] = $message;
        }
        if (!isset($errors['username']) && $this->users->usernameTaken($username)) {
            $errors['username'] = 'That username already exists. Promote it with user:set-role instead.';
        }
        if (!isset($errors['email']) && $this->users->emailTaken($email)) {
            $errors['email'] = 'That email address is already registered.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $id = $this->db->transaction(function () use ($username, $email, $password): int {
            // Re-check inside the transaction so two concurrent bootstraps cannot both succeed.
            $this->db->value("SELECT id FROM users WHERE role = 'admin' LIMIT 1 FOR UPDATE");
            if ($this->users->countAdmins(false) > 0) {
                throw new ValidationException(['admin' => 'An administrator already exists. Bootstrap is disabled.']);
            }
            return $this->users->create($username, $email, PasswordHasher::hash($password), 'active', 'admin');
        });
        $this->audit->record(null, 'admin.bootstrapped', 'user', $id, $username);
        return $id;
    }

    /**
     * @param array<string, mixed> $actor
     * @throws ValidationException
     */
    public function changeStatus(array $actor, int $userId, string $action, string $reason = ''): string
    {
        $definition = self::STATUS_ACTIONS[$action] ?? throw new ValidationException(['action' => 'Unknown account action.']);
        [$from, $to, $auditAction] = $definition;
        $user = $this->users->find($userId) ?? throw new ValidationException(['user' => 'That account no longer exists.']);
        if ((int) $user['id'] === (int) $actor['id']) {
            throw new ValidationException(['user' => 'You cannot change the status of your own account.']);
        }
        if (!in_array($user['status'], $from, true)) {
            throw new ValidationException(['user' => sprintf('A %s account cannot be changed with “%s”.', $user['status'], $action)]);
        }
        if ($user['role'] === 'admin' && $to !== 'active' && $user['status'] === 'active' && $this->users->countAdmins() <= 1) {
            throw new ValidationException(['user' => 'The last active administrator cannot be suspended.']);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) > 500) {
            throw new ValidationException(['reason' => 'Keep the member-facing message under 500 characters.']);
        }
        $this->db->execute(
            'UPDATE users SET status = :status, status_reason = :reason, status_changed_at = :now, status_changed_by = :actor,
                              auth_epoch = auth_epoch + 1, updated_at = :now WHERE id = :id AND status = :previous',
            [
                'status' => $to,
                'reason' => $reason === '' ? null : $reason,
                'now' => Database::now(),
                'actor' => (int) $actor['id'],
                'id' => $userId,
                'previous' => $user['status'],
            ],
        );
        $this->audit->record($actor, $auditAction, 'user', $userId, (string) $user['username'],
            ['from' => $user['status'], 'to' => $to] + ($reason !== '' ? ['reason' => $reason] : []));
        return $to;
    }

    /**
     * @param array<string, mixed> $actor
     * @throws ValidationException
     */
    public function changeRole(array $actor, int $userId, string $role): void
    {
        if (!in_array($role, ['member', 'admin'], true)) {
            throw new ValidationException(['role' => 'Choose Member or Administrator.']);
        }
        $user = $this->users->find($userId) ?? throw new ValidationException(['user' => 'That account no longer exists.']);
        if ($user['role'] === $role) {
            return;
        }
        if ((int) $user['id'] === (int) $actor['id']) {
            throw new ValidationException(['role' => 'You cannot change your own role.']);
        }
        if ($role === 'admin' && $user['status'] !== 'active') {
            throw new ValidationException(['role' => 'Only active accounts can become administrators.']);
        }
        if ($user['role'] === 'admin' && $this->users->countAdmins() <= 1) {
            throw new ValidationException(['role' => 'The last active administrator cannot be demoted.']);
        }
        $this->db->execute(
            'UPDATE users SET role = :role, auth_epoch = auth_epoch + 1, updated_at = :now WHERE id = :id',
            ['role' => $role, 'now' => Database::now(), 'id' => $userId],
        );
        $this->audit->record($actor, 'user.role_changed', 'user', $userId, (string) $user['username'],
            ['from' => $user['role'], 'to' => $role]);
    }

    /**
     * @param array<string, mixed> $actor
     * @throws ValidationException
     */
    public function updateAdminNote(array $actor, int $userId, string $note): void
    {
        $note = trim($note);
        if (mb_strlen($note) > 5000) {
            throw new ValidationException(['admin_note' => 'Keep admin notes under 5,000 characters.']);
        }
        $user = $this->users->find($userId) ?? throw new ValidationException(['user' => 'That account no longer exists.']);
        $this->db->execute('UPDATE users SET admin_note = :note, updated_at = :now WHERE id = :id',
            ['note' => $note === '' ? null : $note, 'now' => Database::now(), 'id' => $userId]);
        $this->audit->record($actor, 'user.note_updated', 'user', $userId, (string) $user['username']);
    }
}
