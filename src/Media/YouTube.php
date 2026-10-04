<?php

declare(strict_types=1);

namespace Uvs\Media;

/**
 * Parses and normalises YouTube video URLs. Only an 11-character video id and an
 * optional start time survive; embeds always use youtube-nocookie.com.
 */
final class YouTube
{
    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtu.be', 'www.youtu.be',
        'youtube-nocookie.com', 'www.youtube-nocookie.com',
    ];

    /**
     * @return array{id: string, start: int}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 300 || preg_match('/[\s<>"\'\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($host, self::HOSTS, true)) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        $path = $parts['path'] ?? '';
        $id = null;
        if (str_ends_with($host, 'youtu.be')) {
            $id = ltrim($path, '/');
        } elseif ($path === '/watch') {
            $id = is_string($query['v'] ?? null) ? $query['v'] : null;
        } elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/]+)/?$#', $path, $match)) {
            $id = $match[1];
        }
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return null;
        }
        $start = 0;
        foreach (['t', 'start'] as $key) {
            if (is_string($query[$key] ?? null)) {
                $start = self::seconds($query[$key]);
                break;
            }
        }
        if ($start === 0 && isset($parts['fragment']) && preg_match('/^t=(.+)$/', $parts['fragment'], $match)) {
            $start = self::seconds($match[1]);
        }
        return ['id' => $id, 'start' => $start];
    }

    private static function seconds(string $value): int
    {
        if (preg_match('/^\d{1,6}s?$/', $value)) {
            return min(86400, (int) $value);
        }
        if (preg_match('/^(?:(\d{1,2})h)?(?:(\d{1,3})m)?(?:(\d{1,5})s)?$/', $value, $match) && $value !== '') {
            return min(86400, ((int) ($match[1] ?? 0)) * 3600 + ((int) ($match[2] ?? 0)) * 60 + (int) ($match[3] ?? 0));
        }
        return 0;
    }

    /**
     * @param array{id: string, start: int} $video
     */
    public static function embedUrl(array $video): string
    {
        return 'https://www.youtube-nocookie.com/embed/' . $video['id'] . ($video['start'] > 0 ? '?start=' . $video['start'] : '');
    }

    /**
     * @param array{id: string, start: int} $video
     */
    public static function watchUrl(array $video): string
    {
        return 'https://www.youtube.com/watch?v=' . $video['id'] . ($video['start'] > 0 ? '&t=' . $video['start'] . 's' : '');
    }
}
