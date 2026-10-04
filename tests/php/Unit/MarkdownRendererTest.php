<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uvs\Guides\MarkdownRenderer;

final class MarkdownRendererTest extends TestCase
{
    private function render(string $markdown, array $media = []): string
    {
        return (new MarkdownRenderer('/media/'))->render($markdown, $media);
    }

    public function testRawHtmlIsEscapedNotRendered(): void
    {
        $html = $this->render("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n<iframe src=\"https://evil.example\"></iframe>");
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;iframe', $html);
    }

    /**
     * @return list<array{string}>
     */
    public static function unsafeLinks(): array
    {
        return [
            ['[x](javascript:alert(1))'],
            ['[x](JaVaScRiPt:alert(1))'],
            ['[x](data:text/html;base64,PHNjcmlwdD4=)'],
            ['[x](vbscript:msgbox(1))'],
            ['[x](//evil.example/path)'],
            ['[x](file:///etc/passwd)'],
            ['[x](https://user:pass@evil.example/)'],
            ['<javascript:alert(1)>'],
        ];
    }

    #[DataProvider('unsafeLinks')]
    public function testUnsafeLinkSchemesAreDropped(string $markdown): void
    {
        $html = $this->render($markdown);
        self::assertDoesNotMatchRegularExpression('/href="(?:javascript|data|vbscript|file|\/\/)/i', $html);
        self::assertDoesNotMatchRegularExpression('/<a [^>]*href="https:\/\/user:pass/', $html);
    }

    public function testSafeLinksGetUgcRel(): void
    {
        $html = $this->render('[Jarulf](https://example.com/guide) and [local](/calculators/) and [mail](mailto:uv@example.com)');
        self::assertMatchesRegularExpression('#<a (?=[^>]*href="https://example\.com/guide")(?=[^>]*rel="nofollow ugc noopener noreferrer")[^>]*>Jarulf</a>#', $html);
        self::assertStringContainsString('<a href="/calculators/">local</a>', $html);
        self::assertStringContainsString('href="mailto:uv@example.com"', $html);
    }

    public function testAttributeInjectionInLinkTitleIsEscaped(): void
    {
        $html = $this->render('[x](https://example.com "a\" onmouseover=\"alert(1)")');
        self::assertStringContainsString('title="a&quot; onmouseover=&quot;alert(1)"', $html);
        self::assertDoesNotMatchRegularExpression('/"\s+onmouseover=/', $html);
    }

    public function testHeadingsNeverProduceAnH1(): void
    {
        $html = $this->render("# Top\n\n## Section\n\n### Sub");
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('<h2>Top</h2>', $html);
        self::assertStringContainsString('<h2>Section</h2>', $html);
        self::assertStringContainsString('<h3>Sub</h3>', $html);
    }

    public function testCallouts(): void
    {
        $html = $this->render("> [!WARNING]\n> Do not sell this.\n\n> [!tip] Inline tip text\n\n> Plain quote");
        self::assertStringContainsString('<aside class="guide-callout guide-callout-warning" aria-label="Warning">', $html);
        self::assertStringContainsString('<p>Do not sell this.</p>', $html);
        self::assertStringContainsString('guide-callout-tip', $html);
        self::assertStringContainsString('Inline tip text', $html);
        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringNotContainsString('[!WARNING]', $html);
    }

    public function testYouTubeBlocksBecomePrivacyEnhancedEmbeds(): void
    {
        $html = $this->render("```youtube\nhttps://www.youtube.com/watch?v=c8MaZZezeMQ&t=1m5s\nMax's video\n```");
        self::assertStringContainsString('src="https://www.youtube-nocookie.com/embed/c8MaZZezeMQ?start=65"', $html);
        self::assertStringContainsString('title="Max\'s video"', $html);
        self::assertStringNotContainsString('src="https://www.youtube.com', $html);
    }

    public function testNonYouTubeEmbedIsRefused(): void
    {
        $html = $this->render("```youtube\nhttps://evil.example/watch?v=c8MaZZezeMQ\n```");
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringContainsString('guide-embed-error', $html);
    }

    public function testCaptionCannotBreakOutOfAttributes(): void
    {
        $html = $this->render("```youtube\nhttps://youtu.be/c8MaZZezeMQ\n\"><script>alert(1)</script>\n```");
        self::assertStringNotContainsString('<script', $html);
    }

    public function testOnlyThisGuidesMediaRenderAsImages(): void
    {
        $media = ['AAAAAAAAAAAAAAAAAAAAAA' => ['extension' => 'webp', 'width' => 640, 'height' => 480]];
        $renderer = new MarkdownRenderer('/media/');
        $html = $renderer->render("![Map](media:AAAAAAAAAAAAAAAAAAAAAA)\n\n![Remote](https://evil.example/x.png)\n\n![Other](media:BBBBBBBBBBBBBBBBBBBBBB)", $media);
        self::assertMatchesRegularExpression('#<img [^>]*src="/media/AAAAAAAAAAAAAAAAAAAAAA\.webp" alt="Map"#', $html);
        self::assertStringContainsString('width="640"', $html);
        self::assertStringNotContainsString('evil.example', $html);
        self::assertStringNotContainsString('BBBBBBBBBBBBBBBBBBBBBB.webp', $html);
        self::assertSame(['AAAAAAAAAAAAAAAAAAAAAA'], $renderer->referencedMedia());
        self::assertNotEmpty($renderer->warnings());
    }

    public function testTablesAreWrappedAndClassed(): void
    {
        $html = $this->render("| a | b |\n|---|---|\n| 1 | 2 |");
        self::assertStringContainsString('<div class="guide-table-wrap"', $html);
        self::assertStringContainsString('<table class="guide-table">', $html);
    }

    public function testSqlLikeTextIsJustText(): void
    {
        $html = $this->render("Robert'); DROP TABLE guides;--");
        self::assertStringContainsString("<p>Robert'); DROP TABLE guides;--</p>", $html);
    }

    public function testMediaReferencesExtraction(): void
    {
        self::assertSame(['AAAAAAAAAAAAAAAAAAAAAA'], MarkdownRenderer::mediaReferences('x ![a](media:AAAAAAAAAAAAAAAAAAAAAA) ![b](media:AAAAAAAAAAAAAAAAAAAAAA)'));
    }
}
