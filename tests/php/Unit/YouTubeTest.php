<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uvs\Media\YouTube;

final class YouTubeTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int}>
     */
    public static function valid(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=c8MaZZezeMQ', 'c8MaZZezeMQ', 0],
            'watch with time' => ['https://www.youtube.com/watch?v=c8MaZZezeMQ&t=90s', 'c8MaZZezeMQ', 90],
            'short link' => ['https://youtu.be/c8MaZZezeMQ?t=1h2m3s', 'c8MaZZezeMQ', 3723],
            'mobile' => ['http://m.youtube.com/watch?v=c8MaZZezeMQ', 'c8MaZZezeMQ', 0],
            'embed' => ['https://www.youtube.com/embed/c8MaZZezeMQ?start=30', 'c8MaZZezeMQ', 30],
            'nocookie' => ['https://www.youtube-nocookie.com/embed/c8MaZZezeMQ', 'c8MaZZezeMQ', 0],
            'shorts' => ['https://youtube.com/shorts/c8MaZZezeMQ', 'c8MaZZezeMQ', 0],
            'live' => ['https://www.youtube.com/live/c8MaZZezeMQ', 'c8MaZZezeMQ', 0],
        ];
    }

    #[DataProvider('valid')]
    public function testParsesSupportedUrls(string $url, string $id, int $start): void
    {
        self::assertSame(['id' => $id, 'start' => $start], YouTube::parse($url));
    }

    /**
     * @return list<array{string}>
     */
    public static function invalid(): array
    {
        return [
            ['https://evil.example/watch?v=c8MaZZezeMQ'],
            ['https://www.youtube.com.evil.example/watch?v=c8MaZZezeMQ'],
            ['javascript:alert(1)//youtube.com/watch?v=c8MaZZezeMQ'],
            ['https://www.youtube.com/watch?v=short'],
            ['https://www.youtube.com/watch?v=c8MaZZezeMQ"onload="x'],
            ['<iframe src="https://www.youtube.com/embed/c8MaZZezeMQ"></iframe>'],
            ['https://user@www.youtube.com/watch?v=c8MaZZezeMQ'],
            ['https://www.youtube.com:8443/watch?v=c8MaZZezeMQ'],
            ['ftp://youtube.com/watch?v=c8MaZZezeMQ'],
            [''],
        ];
    }

    #[DataProvider('invalid')]
    public function testRejectsEverythingElse(string $url): void
    {
        self::assertNull(YouTube::parse($url));
    }

    public function testEmbedAlwaysUsesPrivacyEnhancedHost(): void
    {
        self::assertSame('https://www.youtube-nocookie.com/embed/c8MaZZezeMQ?start=5', YouTube::embedUrl(['id' => 'c8MaZZezeMQ', 'start' => 5]));
        self::assertSame('https://www.youtube-nocookie.com/embed/c8MaZZezeMQ', YouTube::embedUrl(['id' => 'c8MaZZezeMQ', 'start' => 0]));
    }
}
