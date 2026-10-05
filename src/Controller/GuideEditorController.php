<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Guides\GuideStatus;
use Uvs\Guides\GuideWorkflow;
use Uvs\Guides\MarkdownRenderer;
use Uvs\Http\HttpException;
use Uvs\Http\Response;
use Uvs\Support\Text;
use Uvs\Support\ValidationException;

/**
 * Member guide authoring: drafts, autosave, preview, submission, and images.
 * Authorization is checked on every action; authors only ever reach their own
 * guides, and nothing here can change what the public sees.
 */
final class GuideEditorController extends Controller
{
    public function index(): Response
    {
        $user = $this->authorize('account.manage');
        $guides = $this->app->guides()->listForAuthor((int) $user['id']);
        return $this->render('guides/my-guides', [
            'user' => $user,
            'guides' => $guides,
            'submissionsOpen' => $this->app->settings()->bool('guide_submissions_enabled'),
        ], ['title' => 'Your guides', 'current' => 'account']);
    }

    public function createForm(): Response
    {
        $user = $this->authorize('guide.create', null, 'Only approved members can write guides. Your account is awaiting approval.');
        return $this->editorPage($user, null);
    }

    public function create(): Response
    {
        $user = $this->authorize('guide.create', null, 'Only approved members can write guides.');
        $input = $this->contentInput();
        if (!$this->app->rateLimiter()->hit('guide.create', (string) $user['id'])) {
            return $this->editorPage($user, null, ['form' => 'You have started many guides today. Please continue an existing draft.'], $input, 429);
        }
        try {
            $workflow = $this->workflow();
            $id = $workflow->createDraft($user, $workflow->clean($input), (int) $this->app->config->get('guides.max_drafts', 25));
        } catch (ValidationException $error) {
            return $this->editorPage($user, null, $error->errors, $input, 422);
        }
        $this->flash('success', 'Draft created. It stays private until you submit it.');
        return $this->redirect('account/guides/' . $id . '/edit/');
    }

    public function edit(string $id): Response
    {
        $user = $this->requireUser();
        $guide = $this->ownGuide($user, $id);
        return $this->editorPage($user, $guide);
    }

    public function save(string $id): Response
    {
        $user = $this->authorize('guide.create');
        $guide = $this->ownGuide($user, $id);
        $this->authorizeGuide($user, 'guide.edit', $guide);
        $input = $this->contentInput();
        $json = $this->request->wantsJson();
        try {
            $workflow = $this->workflow();
            $updated = $workflow->saveDraft($guide, $workflow->clean($input), (int) $this->request->input('lock_version'));
        } catch (ValidationException $error) {
            if ($json) {
                return Response::json(['error' => $error->first(), 'errors' => $error->errors], isset($error->errors['conflict']) ? 409 : 422);
            }
            return $this->editorPage($user, $guide, $error->errors, $input, isset($error->errors['conflict']) ? 409 : 422);
        }
        if ($json) {
            $status = GuideStatus::describe($updated);
            return Response::json([
                'ok' => true,
                'lock_version' => (int) $updated['lock_version'],
                'saved_at' => gmdate('c'),
                'slug' => $updated['slug'],
                'status' => $status['label'],
            ]);
        }
        if ($this->request->input('intent') === 'submit') {
            return $this->submitGuide($user, $updated, (int) $updated['lock_version']);
        }
        $this->flash('success', 'Draft saved.');
        return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/');
    }

    public function submit(string $id): Response
    {
        $user = $this->authorize('guide.create');
        $guide = $this->ownGuide($user, $id);
        return $this->submitGuide($user, $guide, $this->expectedVersion());
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $guide
     */
    private function submitGuide(array $user, array $guide, ?int $expectedVersion): Response
    {
        $this->authorizeGuide($user, 'guide.submit', $guide);
        if (!$this->app->rateLimiter()->hit('guide.submit', (string) $user['id'])) {
            throw $this->tooManyRequests('You have submitted many guides today. Please try again tomorrow.');
        }
        try {
            $this->workflow()->submit($user, $guide, $expectedVersion);
        } catch (ValidationException $error) {
            $fresh = $this->app->guides()->find((int) $guide['id']) ?? $guide;
            return $this->editorPage($user, $fresh, $error->errors, [], isset($error->errors['conflict']) ? 409 : 422);
        }
        $this->flash('success', 'Submitted for review. An administrator will take a look soon.');
        return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/');
    }

    public function withdraw(string $id): Response
    {
        $user = $this->authorize('guide.create');
        $guide = $this->ownGuide($user, $id);
        try {
            $this->workflow()->withdraw($guide, $this->expectedVersion());
        } catch (ValidationException $error) {
            $this->flash('error', $error->first());
            return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/');
        }
        $this->flash('info', 'Submission withdrawn. The guide is a draft again.');
        return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/');
    }

    public function delete(string $id): Response
    {
        $user = $this->requireUser();
        $guide = $this->ownGuide($user, $id);
        if (!GuideStatus::authorCanDelete($guide)) {
            $this->flash('error', 'Published guides cannot be deleted by their author. Contact an administrator.');
            return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/');
        }
        if ($this->request->input('confirm') !== '1') {
            $this->flash('error', 'Tick the confirmation box to delete this guide.');
            return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/#delete-guide');
        }
        try {
            $media = $this->workflow()->deleteDraft($user, (int) $guide['id'], $this->expectedVersion());
        } catch (ValidationException $error) {
            $this->flash('error', $error->first());
            return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/');
        }
        $this->app->media()->deleteRows($media);
        $this->flash('success', '“' . $guide['title'] . '” was deleted.');
        return $this->redirect('account/guides/');
    }

    public function preview(string $id): Response
    {
        $user = $this->requireUser();
        $guide = $this->app->guides()->find((int) $id);
        if ($guide === null || !$this->app->gate()->allows($user, 'guide.view_private', $guide)) {
            throw HttpException::notFound();
        }
        return $this->render('guides/show', [
            'guide' => $guide,
            'html' => $this->renderer()->render((string) $guide['body'], $this->app->guides()->mediaMap((int) $guide['id'])),
            'preview' => true,
            'status' => GuideStatus::describe($guide),
        ], ['title' => 'Preview: ' . $guide['title'], 'current' => 'guides',
            'styles' => ['css/in-page-navigation.css', 'guides/css/styles.css'], 'scripts' => ['js/in-page-navigation.js']]);
    }

    public function renderPreview(): Response
    {
        $user = $this->authorize('guide.create');
        if (!$this->app->rateLimiter()->hit('preview.user', (string) $user['id'])) {
            return Response::json(['error' => 'Preview is temporarily rate limited.'], 429);
        }
        $body = Text::block($this->request->input('body'), $this->workflow()->maxBodyLength());
        $media = [];
        $guideId = (int) $this->request->input('guide_id');
        if ($guideId > 0) {
            $guide = $this->app->guides()->find($guideId);
            if ($guide !== null && $this->app->gate()->allows($user, 'guide.view_private', $guide)) {
                $media = $this->app->guides()->mediaMap($guideId);
            }
        }
        $renderer = $this->renderer();
        $html = $renderer->render($body, $media);
        return Response::json(['html' => $html, 'warnings' => $renderer->warnings()]);
    }

    public function uploadMedia(string $id): Response
    {
        $user = $this->authorize('media.upload');
        $guide = $this->ownGuide($user, $id);
        $this->authorizeGuide($user, 'guide.edit', $guide);
        $json = $this->request->wantsJson();
        if (!$this->app->rateLimiter()->hit('media.upload', (string) $user['id'])) {
            return $json ? Response::json(['error' => 'Too many uploads. Please wait a while.'], 429)
                : throw $this->tooManyRequests('Too many uploads. Please wait a while before trying again.');
        }
        $alt = Text::line($this->request->input('alt'), 200);
        $media = $this->app->media();
        try {
            $row = $media->storeGuideImage($user, $guide, $media->uploadedPath($this->request->file('image')), $alt);
        } catch (ValidationException $error) {
            if ($json) {
                return Response::json(['error' => $error->first()], 422);
            }
            $this->flash('error', $error->first());
            return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/#guide-images');
        }
        $markdown = '![' . str_replace([']', '['], '', $alt !== '' ? $alt : 'Image') . '](media:' . $row['public_id'] . ')';
        if ($json) {
            return Response::json([
                'public_id' => $row['public_id'],
                'markdown' => $markdown,
                'url' => $this->view->mediaUrl((string) $row['public_id'], (string) $row['extension']),
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
            ]);
        }
        $this->flash('success', 'Image uploaded. Insert it with: ' . $markdown);
        return $this->redirect('account/guides/' . (int) $guide['id'] . '/edit/#guide-images');
    }

    public function deleteMedia(string $publicId): Response
    {
        $user = $this->requireUser();
        try {
            $media = $this->app->media()->deleteGuideImage($user, $publicId, false);
        } catch (ValidationException $error) {
            if ($this->request->wantsJson()) {
                return Response::json(['error' => $error->first()], 422);
            }
            $this->flash('error', $error->first());
            return $this->redirect('account/guides/');
        }
        if ($this->request->wantsJson()) {
            return Response::json(['ok' => true]);
        }
        $this->flash('success', 'Image deleted.');
        return $this->redirect($media['guide_id'] !== null ? 'account/guides/' . (int) $media['guide_id'] . '/edit/#guide-images' : 'account/guides/');
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $guide
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function editorPage(array $user, ?array $guide, array $errors = [], array $old = [], int $status = 200): Response
    {
        $media = $guide !== null ? $this->app->guides()->media((int) $guide['id']) : [];
        $workflow = $this->workflow();
        $limits = $this->app->media()->limits();
        return $this->render('guides/editor', [
            'user' => $user,
            'guide' => $guide,
            'errors' => $errors,
            'old' => $old + ($guide !== null ? [
                'title' => (string) $guide['title'],
                'summary' => (string) $guide['summary'],
                'body' => (string) $guide['body'],
                'applies_to' => (string) ($guide['applies_to'] ?? ''),
            ] : []),
            'status' => $guide !== null ? GuideStatus::describe($guide) : null,
            'canEdit' => $guide === null || ($this->app->gate()->allows($user, 'guide.edit', $guide) && GuideStatus::authorCanEdit($guide)),
            'canSubmit' => $guide !== null && GuideStatus::authorCanSubmit($guide) && $this->app->gate()->allows($user, 'guide.submit', $guide),
            'canWithdraw' => $guide !== null && GuideStatus::authorCanWithdraw($guide),
            'canDelete' => $guide !== null && GuideStatus::authorCanDelete($guide),
            'submissionsOpen' => $this->app->settings()->bool('guide_submissions_enabled'),
            'media' => $media,
            'limits' => $limits,
            'usage' => $this->app->media()->usage((int) $user['id']),
            'appliesTo' => GuideWorkflow::APPLIES_TO,
            'maxBody' => $workflow->maxBodyLength(),
        ], [
            'title' => $guide === null ? 'Write a guide' : 'Edit: ' . $guide['title'],
            'current' => 'account',
            'styles' => ['guides/css/styles.css'],
            'scripts' => ['js/guide-editor.js'],
        ], $status);
    }

    /** The guide version a form was rendered from, when the form supplied one. */
    private function expectedVersion(): ?int
    {
        $value = filter_var($this->request->input('lock_version'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function contentInput(): array
    {
        return [
            'title' => $this->request->input('title'),
            'summary' => $this->request->input('summary'),
            'body' => $this->request->input('body'),
            'applies_to' => $this->request->input('applies_to'),
        ];
    }

    /**
     * Loads a guide owned by the user. Other people's guides are reported as
     * missing so their existence is not revealed.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function ownGuide(array $user, string $id): array
    {
        $guide = $this->app->guides()->find((int) $id);
        if ($guide === null || $guide['author_id'] === null || (int) $guide['author_id'] !== (int) $user['id'] || $guide['deleted_at'] !== null) {
            throw HttpException::notFound();
        }
        return $guide;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $guide
     */
    private function authorizeGuide(array $user, string $ability, array $guide): void
    {
        if (!$this->app->gate()->allows($user, $ability, $guide)) {
            throw HttpException::forbidden('Only approved members can change or submit guides.');
        }
    }

    private function workflow(): GuideWorkflow
    {
        return $this->app->guideWorkflow();
    }

    private function renderer(): MarkdownRenderer
    {
        return $this->app->markdown($this->request->url('media/'));
    }
}
