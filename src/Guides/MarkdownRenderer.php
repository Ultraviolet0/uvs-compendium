<?php

declare(strict_types=1);

namespace Uvs\Guides;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Block\BlockQuoteRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Table\TableRenderer;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Uvs\Media\YouTube;

/**
 * Safe Markdown for community guides, built on league/commonmark.
 *
 * Security properties come from the parser configuration and AST handling, not
 * from string filtering: raw HTML is escaped, unsafe link schemes are dropped,
 * images may only reference this site's uploaded media, and video embeds are
 * produced solely from validated YouTube identifiers.
 *
 * Extra syntax:
 *   > [!NOTE] / [!TIP] / [!WARNING]   callout boxes
 *   ```youtube                         privacy-enhanced YouTube embed
 *   https://youtu.be/VIDEO_ID
 *   Optional caption
 *   ```
 *   ![Alt text](media:PUBLIC_ID)       an image uploaded to this guide
 */
final class MarkdownRenderer
{
    public const CALLOUTS = ['NOTE' => 'note', 'TIP' => 'tip', 'WARNING' => 'warning'];
    private const CALLOUT_TITLES = ['note' => 'Note', 'tip' => 'Tip', 'warning' => 'Warning'];

    private MarkdownConverter $converter;

    /** @var array<string, array{extension: string, width: int, height: int}> */
    private array $media = [];

    /** @var list<string> */
    private array $referencedMedia = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(private readonly string $mediaBaseUrl)
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'max_delimiters_per_line' => 500,
            'renderer' => ['soft_break' => "\n"],
            'commonmark' => ['enable_em' => true, 'enable_strong' => true, 'use_asterisk' => true, 'use_underscore' => true],
            'table' => ['wrap' => ['enabled' => false]],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addEventListener(DocumentParsedEvent::class, $this->transform(...));
        $environment->addRenderer(FencedCode::class, $this->fencedCodeRenderer(), 10);
        $environment->addRenderer(BlockQuote::class, $this->blockQuoteRenderer(), 10);
        $environment->addRenderer(Table::class, $this->tableRenderer(), 10);
        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @param array<string, array{extension: string, width: int, height: int}> $media public id => metadata
     *        for images that belong to the guide being rendered
     */
    public function render(string $markdown, array $media = []): string
    {
        $this->media = $media;
        $this->referencedMedia = [];
        $this->warnings = [];
        return (string) $this->converter->convert($markdown);
    }

    /** @return list<string> media public ids referenced by the last render */
    public function referencedMedia(): array
    {
        return array_values(array_unique($this->referencedMedia));
    }

    /** @return list<string> author-facing notes about content that was not rendered */
    public function warnings(): array
    {
        return array_values(array_unique($this->warnings));
    }

    /**
     * Extracts media ids referenced with the media: scheme, without rendering.
     *
     * @return list<string>
     */
    public static function mediaReferences(string $markdown): array
    {
        preg_match_all('/\(media:([A-Za-z0-9_-]{22})\)/', $markdown, $matches);
        return array_values(array_unique($matches[1]));
    }

    private function transform(DocumentParsedEvent $event): void
    {
        $walker = $event->getDocument()->walker();
        $unwrap = [];
        $images = [];
        while ($walkerEvent = $walker->next()) {
            if (!$walkerEvent->isEntering()) {
                continue;
            }
            $node = $walkerEvent->getNode();
            if ($node instanceof Heading) {
                // The page title is the only h1; guide sections start at h2.
                $node->setLevel(min(6, $node->getLevel() + 1));
            } elseif ($node instanceof ListBlock) {
                $class = $node->getListData()->type === ListBlock::TYPE_ORDERED ? 'guide-list guide-list-ordered' : 'guide-list';
                $node->data->set('attributes/class', $class);
            } elseif ($node instanceof Table) {
                $node->data->set('attributes/class', 'guide-table');
            } elseif ($node instanceof BlockQuote) {
                $this->markCallout($node);
            } elseif ($node instanceof Image) {
                $images[] = $node;
            } elseif ($node instanceof Link) {
                $url = self::safeLinkUrl($node->getUrl());
                if ($url === null) {
                    $unwrap[] = $node;
                    $this->warnings[] = 'A link with an unsupported address was shown as plain text.';
                    continue;
                }
                $node->setUrl($url);
                if (preg_match('#^https?://#i', $url)) {
                    $node->data->set('attributes/rel', 'nofollow ugc noopener noreferrer');
                }
            }
        }
        foreach ($unwrap as $link) {
            self::unwrap($link);
        }
        foreach ($images as $image) {
            $this->resolveImage($image);
        }
    }

    private function markCallout(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        if (!$paragraph instanceof Paragraph) {
            return;
        }
        // Unmatched brackets are parsed as separate text nodes, so read the
        // leading run of text nodes as one string.
        $texts = [];
        $leading = '';
        for ($node = $paragraph->firstChild(); $node instanceof Text; $node = $node->next()) {
            $texts[] = $node;
            $leading .= $node->getLiteral();
        }
        if (!preg_match('/^\[!(NOTE|TIP|WARNING)\][ \t]*/i', $leading, $match)) {
            return;
        }
        $quote->data->set('callout', self::CALLOUTS[strtoupper($match[1])]);
        $remaining = strlen($match[0]);
        foreach ($texts as $text) {
            $length = strlen($text->getLiteral());
            if ($remaining >= $length) {
                $remaining -= $length;
                $text->detach();
                continue;
            }
            $text->setLiteral(substr($text->getLiteral(), $remaining));
            $remaining = 0;
            break;
        }
        // Drop the line break that followed the marker.
        $first = $paragraph->firstChild();
        if ($first instanceof \League\CommonMark\Node\Inline\Newline) {
            $first->detach();
        }
        if ($paragraph->firstChild() === null) {
            $paragraph->detach();
        }
    }

    private function resolveImage(Image $image): void
    {
        $url = $image->getUrl();
        if (preg_match('/^media:([A-Za-z0-9_-]{22})$/', $url, $match) && isset($this->media[$match[1]])) {
            $meta = $this->media[$match[1]];
            $this->referencedMedia[] = $match[1];
            $image->setUrl($this->mediaBaseUrl . $match[1] . '.' . $meta['extension']);
            $image->data->set('attributes/class', 'guide-inline-image');
            $image->data->set('attributes/loading', 'lazy');
            $image->data->set('attributes/decoding', 'async');
            $image->data->set('attributes/width', (string) $meta['width']);
            $image->data->set('attributes/height', (string) $meta['height']);
            return;
        }
        $this->warnings[] = 'Images must be uploaded to this guide; other image addresses are not displayed.';
        $alt = '';
        foreach ($image->children() as $child) {
            if ($child instanceof Text) {
                $alt .= $child->getLiteral();
            }
        }
        $replacement = new Text($alt !== '' ? '[Image: ' . $alt . ']' : '[Image unavailable]');
        $image->replaceWith($replacement);
    }

    /** Moves a node's children into its place, removing the node itself. */
    private static function unwrap(Node $node): void
    {
        foreach ($node->children() as $child) {
            $node->insertBefore($child);
        }
        $node->detach();
    }

    /**
     * Permits http(s), mailto, same-site paths, and fragments. Anything else
     * (javascript:, data:, vbscript:, file:, protocol-relative, ...) is refused.
     */
    public static function safeLinkUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7F]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '#')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') || str_contains($url, '\\') ? null : $url;
        }
        if (preg_match('#^(https?)://[^/\s?#@]+#i', $url)) {
            $parts = parse_url($url);
            if ($parts === false || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
            return $url;
        }
        if (preg_match('/^mailto:[^\s<>]+@[^\s<>]+$/i', $url)) {
            return $url;
        }
        return null;
    }

    private function fencedCodeRenderer(): NodeRendererInterface
    {
        $default = new FencedCodeRenderer();
        return new class ($default) implements NodeRendererInterface {
            public function __construct(private readonly FencedCodeRenderer $default)
            {
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
            {
                /** @var FencedCode $node */
                if (strtolower(trim((string) $node->getInfo())) !== 'youtube') {
                    return $this->default->render($node, $childRenderer);
                }
                $lines = array_values(array_filter(array_map('trim', explode("\n", $node->getLiteral())), 'strlen'));
                $video = YouTube::parse($lines[0] ?? '');
                $caption = mb_substr(implode(' ', array_slice($lines, 1)), 0, 200);
                if ($video === null) {
                    return new HtmlElement('p', ['class' => 'guide-embed-error'],
                        'This YouTube embed could not be shown because its address is not a supported YouTube video link.');
                }
                $iframe = new HtmlElement('iframe', [
                    'src' => YouTube::embedUrl($video),
                    'title' => $caption !== '' ? $caption : 'Embedded YouTube video',
                    'loading' => 'lazy',
                    'referrerpolicy' => 'strict-origin-when-cross-origin',
                    'allow' => 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                    'allowfullscreen' => true,
                ], '');
                $children = [new HtmlElement('div', ['class' => 'guide-video-embed'], $iframe)];
                $link = new HtmlElement('a', ['href' => YouTube::watchUrl($video), 'rel' => 'noopener noreferrer'], 'Watch on YouTube');
                $children[] = new HtmlElement('figcaption', ['class' => 'guide-figcaption'],
                    ($caption !== '' ? htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . ' · ' : '') . $link);
                return new HtmlElement('figure', ['class' => 'guide-figure guide-video-figure'], $children);
            }
        };
    }

    private function blockQuoteRenderer(): NodeRendererInterface
    {
        $default = new BlockQuoteRenderer();
        return new class ($default, self::CALLOUT_TITLES) implements NodeRendererInterface {
            /**
             * @param array<string, string> $titles
             */
            public function __construct(private readonly BlockQuoteRenderer $default, private readonly array $titles)
            {
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
            {
                $type = $node->data->get('callout', null);
                if (!is_string($type) || !isset($this->titles[$type])) {
                    return $this->default->render($node, $childRenderer);
                }
                $title = new HtmlElement('p', ['class' => 'guide-callout-title'], $this->titles[$type]);
                return new HtmlElement('aside', ['class' => 'guide-callout guide-callout-' . $type, 'aria-label' => $this->titles[$type]],
                    "\n" . $title . "\n" . $childRenderer->renderNodes($node->children()) . "\n");
            }
        };
    }

    private function tableRenderer(): NodeRendererInterface
    {
        $default = new TableRenderer();
        return new class ($default) implements NodeRendererInterface {
            public function __construct(private readonly TableRenderer $default)
            {
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
            {
                return new HtmlElement('div', ['class' => 'guide-table-wrap', 'tabindex' => '0', 'role' => 'region', 'aria-label' => 'Table'],
                    $this->default->render($node, $childRenderer));
            }
        };
    }
}
