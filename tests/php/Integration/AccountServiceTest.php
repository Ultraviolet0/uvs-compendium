<?php

declare(strict_types=1);

namespace Uvs\Tests\Integration;

use Uvs\Support\ValidationException;

final class AccountServiceTest extends DatabaseTestCase
{
    public function testRegistrationDefaultsToPendingAndKeepsDisplayCasing(): void
    {
        $result = $this->application->accounts()->register('GainTrain', 'gain@example.test', 'correct horse battery staple');
        self::assertSame('pending', $result['status']);
        $user = $this->application->users()->find($result['id']);
        self::assertSame('GainTrain', $user['username']);
        self::assertSame('gaintrain', $user['username_key']);
        self::assertNotSame('correct horse battery staple', $user['password_hash']);
        self::assertSame('member', $user['role']);
        $audit = $this->db->one("SELECT * FROM audit_events WHERE action = 'user.registered'");
        self::assertNotNull($audit);
        self::assertStringNotContainsString('correct horse', (string) $audit['metadata']);
    }

    public function testDuplicateUsernamesAreCaseInsensitive(): void
    {
        $this->application->accounts()->register('Maxpire', 'max@example.test', 'correct horse battery staple');
        try {
            $this->application->accounts()->register('MAXPIRE', 'other@example.test', 'correct horse battery staple');
            self::fail('Expected duplicate username to be rejected');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('username', $error->errors);
        }
    }

    public function testDuplicateEmailIsRejectedCaseInsensitively(): void
    {
        $this->application->accounts()->register('First', 'Shared@Example.test', 'correct horse battery staple');
        try {
            $this->application->accounts()->register('Second', 'shared@example.TEST', 'correct horse battery staple');
            self::fail('Expected duplicate email to be rejected');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('email', $error->errors);
        }
    }

    public function testInvalidInputAndSqlLikeValuesAreHarmless(): void
    {
        try {
            $this->application->accounts()->register("x' OR '1'='1", "bad'; DROP TABLE users;--@x", 'short');
            self::fail('Expected validation errors');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('username', $error->errors);
            self::assertArrayHasKey('email', $error->errors);
            self::assertArrayHasKey('password', $error->errors);
        }
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM users'));
        self::assertNull($this->application->users()->findByLogin("' OR '1'='1"));
    }

    public function testAutomaticApprovalOnlyAffectsNewSignups(): void
    {
        $admin = $this->user('Boss', 'active', 'admin');
        $old = $this->application->accounts()->register('Waiting', 'wait@example.test', 'correct horse battery staple');
        $rejected = $this->application->accounts()->register('Spammer', 'spam@example.test', 'correct horse battery staple');
        $this->application->accounts()->changeStatus($admin, $rejected['id'], 'reject');
        $this->application->settings()->set('auto_approve_accounts', '1', (int) $admin['id']);
        $new = $this->application->accounts()->register('Newcomer', 'new@example.test', 'correct horse battery staple');
        self::assertSame('active', $new['status']);
        self::assertSame('pending', $this->application->users()->find($old['id'])['status']);
        self::assertSame('rejected', $this->application->users()->find($rejected['id'])['status']);
    }

    public function testStatusTransitionsAndSessionRevocation(): void
    {
        $admin = $this->user('Boss', 'active', 'admin');
        $member = $this->application->accounts()->register('Rogue', 'rogue@example.test', 'correct horse battery staple');
        $epoch = (int) $this->application->users()->find($member['id'])['auth_epoch'];
        self::assertSame('active', $this->application->accounts()->changeStatus($admin, $member['id'], 'approve'));
        self::assertSame('suspended', $this->application->accounts()->changeStatus($admin, $member['id'], 'suspend', 'Spam links'));
        $suspended = $this->application->users()->find($member['id']);
        self::assertGreaterThan($epoch, (int) $suspended['auth_epoch']);
        self::assertSame('Spam links', $suspended['status_reason']);
        self::assertSame('active', $this->application->accounts()->changeStatus($admin, $member['id'], 'reactivate'));
        $this->expectException(ValidationException::class);
        $this->application->accounts()->changeStatus($admin, $member['id'], 'reject');
    }

    public function testAdminsCannotLockThemselvesOut(): void
    {
        $admin = $this->user('Boss', 'active', 'admin');
        $other = $this->user('Helper', 'active', 'admin');
        try {
            $this->application->accounts()->changeStatus($admin, (int) $admin['id'], 'suspend');
            self::fail('Self-suspension must be refused');
        } catch (ValidationException) {
        }
        $this->application->accounts()->changeRole($admin, (int) $other['id'], 'member');
        try {
            $this->application->accounts()->changeRole($admin, (int) $admin['id'], 'member');
            self::fail('Self-demotion must be refused');
        } catch (ValidationException) {
        }
        self::assertSame(1, $this->application->users()->countAdmins());
    }

    public function testBootstrapCreatesOnlyTheFirstAdministrator(): void
    {
        $id = $this->application->accounts()->bootstrapAdmin('Ultraviolet', 'owner@example.test', 'correct horse battery staple');
        $admin = $this->application->users()->find($id);
        self::assertSame('admin', $admin['role']);
        self::assertSame('active', $admin['status']);
        $this->expectException(ValidationException::class);
        $this->application->accounts()->bootstrapAdmin('Second', 'second@example.test', 'correct horse battery staple');
    }

    public function testReservedOwnerNameCannotBeRegisteredPublicly(): void
    {
        $this->expectException(ValidationException::class);
        $this->application->accounts()->register('ultraviolet', 'squatter@example.test', 'correct horse battery staple');
    }

    public function testPasswordResetTokensAreHashedSingleUseAndRevokeSessions(): void
    {
        $member = $this->user('Forgetful');
        $reset = new \Uvs\Users\PasswordResetService($this->db, $this->application->users(), $this->application->mailer(), $this->application->passwordPolicy());
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->execute("INSERT INTO account_tokens (user_id, purpose, token_hash, created_at, expires_at) VALUES (:id, 'password_reset', :hash, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 1 HOUR)",
            ['id' => (int) $member['id'], 'hash' => hash('sha256', $token)]);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM account_tokens WHERE token_hash = :t', ['t' => $token]), 'plaintext token is never stored');
        self::assertNotNull($reset->userForToken($token));
        $reset->complete($token, 'a brand new passphrase here', 'a brand new passphrase here');
        $updated = $this->application->users()->find((int) $member['id']);
        self::assertTrue(password_verify('a brand new passphrase here', (string) $updated['password_hash']));
        self::assertGreaterThan((int) $member['auth_epoch'], (int) $updated['auth_epoch']);
        self::assertNull($reset->userForToken($token));
        $this->expectException(ValidationException::class);
        $reset->complete($token, 'another passphrase entirely', 'another passphrase entirely');
    }

    public function testExpiredResetTokenIsRefused(): void
    {
        $member = $this->user('Late');
        $token = str_repeat('A', 43);
        $this->db->execute("INSERT INTO account_tokens (user_id, purpose, token_hash, created_at, expires_at) VALUES (:id, 'password_reset', :hash, UTC_TIMESTAMP() - INTERVAL 2 HOUR, UTC_TIMESTAMP() - INTERVAL 1 HOUR)",
            ['id' => (int) $member['id'], 'hash' => hash('sha256', $token)]);
        self::assertNull($this->application->passwordResets()->userForToken($token));
    }

    public function testRecoveryCodesAreHashedAndSingleUse(): void
    {
        $member = $this->user('Careful');
        $mfa = $this->application->mfa();
        $secret = \Uvs\Auth\Mfa::newSecret();
        $codes = $mfa->enable((int) $member['id'], $secret, 1);
        self::assertCount(10, $codes);
        $stored = $this->db->value('SELECT mfa_secret FROM users WHERE id = :id', ['id' => (int) $member['id']]);
        self::assertStringNotContainsString($secret, (string) $stored);
        foreach ($this->db->all('SELECT code_hash FROM mfa_recovery_codes') as $row) {
            self::assertNotContains($row['code_hash'], $codes);
        }
        $user = $this->application->users()->find((int) $member['id']);
        self::assertSame('recovery', $mfa->verify($user, strtoupper($codes[0])));
        self::assertNull($mfa->verify($user, $codes[0]), 'recovery codes work once');
        self::assertSame(9, $mfa->remainingRecoveryCodes((int) $member['id']));
    }
}
