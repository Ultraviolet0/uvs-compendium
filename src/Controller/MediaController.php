<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Http\HttpException;
use Uvs\Http\Response;

/**
 * Serves uploaded images from private storage. Files are never executed, are
 * sent with their recorded image type and nosniff, and are sandboxed by CSP.
 */
final class MediaController extends Controller
{
    public function show(string $publicId, string $extension): Response
    {
        $media = $this->app->media()->findPublic($publicId, $extension);
        if ($media === null) {
            throw HttpException::notFound();
        }
        $public = $media['purpose'] === 'avatar'
            ? $media['owner_status'] === 'active'
            : $media['guide_id'] !== null && $media['guide_visibility'] === 'published' && $media['guide_deleted_at'] === null;
        if (!$public) {
            $viewer = $this->user();
            $allowed = $viewer !== null && (
                (int) $media['owner_id'] === (int) $viewer['id']
                || ($media['guide_author_id'] !== null && (int) $media['guide_author_id'] === (int) $viewer['id'])
                || $this->app->gate()->allows($viewer, $media['purpose'] === 'avatar' ? 'users.manage' : 'guides.moderate')
            );
            if (!$allowed) {
                throw HttpException::notFound();
            }
        }
        $path = $this->app->media()->path($publicId, $extension);
        if (!is_file($path)) {
            $this->app->logger()->warning('Media file missing from storage', ['media' => $publicId]);
            throw HttpException::notFound();
        }
        $etag = '"' . substr((string) $media['sha256'], 0, 32) . '"';
        $headers = [
            'Content-Type' => (string) $media['mime_type'],
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'inline; filename="' . $publicId . '.' . $extension . '"',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => $public ? 'public, max-age=86400' : 'private, no-store',
            'ETag' => $etag,
        ];
        if (!$public) {
            $headers['X-Robots-Tag'] = 'noindex';
        }
        if ($public && trim((string) $this->request->header('If-None-Match')) === $etag) {
            unset($headers['Content-Length']);
            return new Response('', 304, $headers);
        }
        return Response::file($path, $headers);
    }
}
