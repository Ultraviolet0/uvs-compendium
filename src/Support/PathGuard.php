<?php

declare(strict_types=1);

namespace Uvs\Support;

/**
 * Decides whether a filesystem path is safely outside the web document root.
 *
 * Existing paths are canonicalised with realpath(), so symbolic links that
 * resolve back into the document root are detected. For a path that does not
 * exist yet, the nearest existing ancestor is canonicalised and the remaining,
 * not-yet-created components are appended; any "." or ".." in that remainder is
 * refused rather than guessed at.
 */
final class PathGuard
{
    /**
     * Returns the canonical form of a path, resolving symlinks in the part that
     * exists, or null when it cannot be determined safely.
     */
    public static function canonical(string $path): ?string
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")) {
            return null;
        }
        $path = rtrim($path, '/') ?: '/';
        $pending = [];
        $current = $path;
        while (true) {
            $real = @realpath($current);
            if ($real !== false) {
                break;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                return null;
            }
            $component = basename($current);
            if ($component === '.' || $component === '..' || $component === '') {
                return null;
            }
            array_unshift($pending, $component);
            $current = $parent;
        }
        foreach ($pending as $component) {
            $real = rtrim($real, '/') . '/' . $component;
        }
        return $real;
    }

    /** Whether $path is $root itself or lies anywhere beneath it, after canonicalisation. */
    public static function isInside(string $path, string $root): bool
    {
        $canonicalPath = self::canonical($path);
        $canonicalRoot = self::canonical($root);
        if ($canonicalPath === null || $canonicalRoot === null) {
            return true; // Unknown is treated as unsafe.
        }
        $canonicalRoot = rtrim($canonicalRoot, '/');
        return $canonicalPath === $canonicalRoot || str_starts_with($canonicalPath, $canonicalRoot . '/');
    }

    /** Whether $path is absolute, resolvable, and outside $documentRoot. */
    public static function isOutside(string $path, string $documentRoot): bool
    {
        return self::canonical($path) !== null && !self::isInside($path, $documentRoot);
    }
}
