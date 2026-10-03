<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uvs\Auth\PasswordHasher;
use Uvs\Auth\PasswordPolicy;
use Uvs\Guides\Slugger;
use Uvs\Users\EmailAddress;
use Uvs\Users\UsernamePolicy;

final class PoliciesTest extends TestCase
{
    public function testUsernameRules(): void
    {
        self::assertSame([], UsernamePolicy::validate('Gain_Train-42'));
        self::assertNotSame([], UsernamePolicy::validate('ab'));
        self::assertNotSame([], UsernamePolicy::validate('_leading'));
        self::assertNotSame([], UsernamePolicy::validate('has space'));
        self::assertNotSame([], UsernamePolicy::validate("x'; DROP TABLE users;--"));
        self::assertNotSame([], UsernamePolicy::validate(str_repeat('a', 25)));
        self::assertNotSame([], UsernamePolicy::validate('Ünïcode'));
    }

    public function testReservedUsernamesIncludingTheOwnerName(): void
    {
        foreach (['admin', 'Administrator', 'ROOT', 'system', 'moderator', 'Ultraviolet', 'the_admin', 'mod-erator', 'UltraViolet99'] as $name) {
            self::assertTrue(UsernamePolicy::isReserved($name), $name);
            self::assertNotSame([], UsernamePolicy::validate($name), $name);
        }
        self::assertSame([], UsernamePolicy::validate('Ultraviolet', allowReserved: true));
        self::assertFalse(UsernamePolicy::isReserved('Maxpire'));
    }

    public function testUsernameNormalisationIsCaseInsensitive(): void
    {
        self::assertSame(UsernamePolicy::normalize('GainTrain'), UsernamePolicy::normalize('gaintrain'));
    }

    public function testPasswordPolicyFavoursPassphrases(): void
    {
        $policy = new PasswordPolicy(10, 256);
        self::assertSame([], $policy->validate('correct horse battery staple'));
        self::assertNotSame([], $policy->validate('short'));
        self::assertNotSame([], $policy->validate('password123'));
        self::assertNotSame([], $policy->validate('aaaaaaaaaaaa'));
        self::assertNotSame([], $policy->validate('SomeUser123', 'someuser123'));
        self::assertNotSame([], $policy->validate(str_repeat('ab', 200)));
    }

    public function testPasswordHashingUsesPhpPasswordApi(): void
    {
        $hash = PasswordHasher::hash('correct horse battery staple');
        self::assertTrue(PasswordHasher::verify('correct horse battery staple', $hash));
        self::assertFalse(PasswordHasher::verify('wrong horse battery staple', $hash));
        self::assertFalse(PasswordHasher::needsRehash($hash));
        self::assertTrue(PasswordHasher::needsRehash(password_hash('x', PASSWORD_BCRYPT, ['cost' => 4])) || PasswordHasher::usesBcrypt());
        self::assertStringNotContainsString('correct horse', $hash);
    }

    public function testEmailValidation(): void
    {
        self::assertTrue(EmailAddress::isValid('player@example.com'));
        self::assertFalse(EmailAddress::isValid('not-an-email'));
        self::assertFalse(EmailAddress::isValid("a@example.com\nBcc: x@example.com"));
        self::assertSame('player@example.com', EmailAddress::normalize('  Player@Example.COM '));
    }

    public function testSlugs(): void
    {
        self::assertSame('kings-bastard-sword-of-haste', Slugger::slugify("King's Bastard Sword of Haste!"));
        self::assertSame('cafe-shopping-and-more', Slugger::slugify('Café shopping & more'));
        self::assertSame('guide', Slugger::slugify('!!!'));
        self::assertLessThanOrEqual(Slugger::MAX_LENGTH, strlen(Slugger::slugify(str_repeat('word ', 40))));
        self::assertTrue(Slugger::isValid('a-b-c'));
        self::assertFalse(Slugger::isValid('A-b'));
        self::assertFalse(Slugger::isValid('a--b'));
        self::assertFalse(Slugger::isValid('../etc'));
    }

    public function testExistingGuideDirectoriesAndSystemWordsAreReserved(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['shopping', 'fast-character-development', 'max-shopping-video', 'template', 'css', 'admin', 'members'] as $slug) {
            self::assertTrue(Slugger::isReserved($slug, $root), $slug);
        }
        self::assertFalse(Slugger::isReserved('my-new-guide', $root));
    }
}
