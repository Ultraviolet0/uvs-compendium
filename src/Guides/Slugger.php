<?php

declare(strict_types=1);

namespace Uvs\Guides;

/**
 * Guide URL slugs: lowercase ASCII words joined by hyphens. Slugs may not
 * shadow a source-controlled guide directory or a reserved word.
 */
final class Slugger
{
    public const MAX_LENGTH = 80;

    public const RESERVED = [
        'account', 'admin', 'api', 'assets', 'calculators', 'community', 'css', 'drafts', 'edit', 'feed',
        'guide', 'guides', 'images', 'index', 'js', 'media', 'members', 'new', 'page', 'preview',
        'privacy', 'reference', 'rss', 'search', 'submit', 'template', 'videos',
    ];

    public static function slugify(string $title): string
    {
        $text = $title;
        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_KD);
            $text = (string) preg_replace('/\p{Mn}+/u', '', $text);
        }
        $text = strtr($text, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
            'ç' => 'c', 'Ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'ñ' => 'n', 'Ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'y',
        ]);
        $text = strtr($text, ['ß' => 'ss', 'æ' => 'ae', 'Æ' => 'ae', 'ø' => 'o', 'Ø' => 'o', 'œ' => 'oe', 'Œ' => 'oe', 'đ' => 'd', 'ł' => 'l', 'Ł' => 'l', '&' => ' and ', "'" => '', '’' => '']);
        $text = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text));
        $text = trim($text, '-');
        if (strlen($text) > self::MAX_LENGTH) {
            $text = substr($text, 0, self::MAX_LENGTH);
            $cut = strrpos($text, '-');
            if ($cut !== false && $cut > 20) {
                $text = substr($text, 0, $cut);
            }
            $text = rtrim($text, '-');
        }
        return $text === '' ? 'guide' : $text;
    }

    public static function isValid(string $slug): bool
    {
        return strlen($slug) <= self::MAX_LENGTH && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }

    /** True when a slug would collide with a reserved word or a physical guide directory. */
    public static function isReserved(string $slug, string $appRoot): bool
    {
        return in_array($slug, self::RESERVED, true) || is_dir($appRoot . '/guides/' . $slug)
            || file_exists($appRoot . '/guides/' . $slug);
    }
}
