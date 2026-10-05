<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Http\HttpException;
use Uvs\Http\Response;

/**
 * Public community guide pages. Only the published revision snapshot of a guide
 * that is published and not deleted is ever rendered here; every other state
 * is indistinguishable from a missing page.
 */
final class GuideController extends Controller
{
    public function show(string $slug): Response
    {
        $guide = $this->app->guides()->findPublishedBySlug($slug);
        if ($guide === null) {
            throw HttpException::notFound();
        }
        $html = $this->app->markdown($this->request->url('media/'))
            ->render((string) $guide['body'], $this->app->guides()->mediaMap((int) $guide['id']));
        return $this->render('guides/show', [
            'guide' => $guide,
            'html' => $html,
            'preview' => false,
            'status' => null,
        ], [
            'title' => (string) $guide['title'],
            'description' => (string) $guide['summary'],
            'current' => 'guides',
            'robots' => 'index',
            'styles' => ['css/in-page-navigation.css', 'guides/css/styles.css'],
            'scripts' => ['js/in-page-navigation.js'],
        ]);
    }
}
