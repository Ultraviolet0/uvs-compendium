<?php

declare(strict_types=1);

namespace Uvs\Controller\Admin;

use Uvs\Guides\GuideRepository;
use Uvs\Guides\GuideStatus;
use Uvs\Guides\GuideWorkflow;
use Uvs\Guides\LineDiff;
use Uvs\Http\HttpException;
use Uvs\Http\Response;
use Uvs\Support\ValidationException;

final class GuideAdminController extends AdminController
{
    private const PER_PAGE = 25;
    private const CONFIRM_REQUIRED = ['reject', 'hide', 'delete', 'purge'];

    public function submissions(): Response
    {
        $this->admin('guides.moderate');
        return $this->redirect('admin/guides/?filter=queue');
    }

    public function index(): Response
    {
        $this->admin('guides.moderate');
        $filter = $this->request->query('filter', 'queue');
        $filter = isset(GuideRepository::ADMIN_FILTERS[$filter]) ? $filter : 'queue';
        $query = mb_substr(trim($this->request->query('q')), 0, 100);
        $page = $this->request->queryInt('page');
        $result = $this->app->guides()->adminList($filter, $query, $page, self::PER_PAGE);
        return $this->adminPage('guides', 'guides', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'filter' => $filter,
            'query' => $query,
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'counts' => $this->app->guides()->adminCounts(),
            'filters' => GuideRepository::ADMIN_FILTERS,
        ], ['title' => 'Guides']);
    }

    /**
     * @param array<string, string> $errors
     */
    public function show(string $id, array $errors = [], int $status = 200): Response
    {
        $admin = $this->admin('guides.moderate');
        $guide = $this->guide($id);
        $repo = $this->app->guides();
        $media = $repo->mediaMap((int) $guide['id']);
        $renderer = $this->app->markdown($this->request->url('media/'));
        $html = $renderer->render((string) $guide['body'], $media);
        $published = null;
        $changes = null;
        if ($guide['published_revision_id'] !== null) {
            $published = $repo->revision((int) $guide['id'], (int) $guide['published_revision_id']);
            if ($published !== null && !hash_equals((string) $published['content_hash'], GuideWorkflow::contentHash($guide))) {
                $changes = LineDiff::withContext(LineDiff::compare((string) $published['body'], (string) $guide['body']));
            }
        }
        $actions = $this->app->guideWorkflow()->availableActions($guide);
        if (!$this->app->gate()->allows($admin, 'guides.purge')) {
            $actions = array_values(array_diff($actions, ['purge']));
        }
        return $this->adminPage('guide', 'guides', [
            'guide' => $guide,
            'html' => $html,
            'warnings' => $renderer->warnings(),
            'status' => GuideStatus::describe($guide),
            'revisions' => $repo->revisions((int) $guide['id']),
            'published' => $published,
            'changes' => $changes,
            'actions' => $actions,
            'labels' => GuideWorkflow::ADMIN_ACTIONS,
            'confirmRequired' => self::CONFIRM_REQUIRED,
            'history' => $this->app->audit()->forTarget('guide', (int) $guide['id']),
            'media' => $repo->media((int) $guide['id']),
            'errors' => $errors,
        ], [
            'title' => 'Review: ' . $guide['title'],
            'styles' => ['guides/css/styles.css'],
        ], $status);
    }

    public function edit(string $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $this->admin('guides.moderate');
        $guide = $this->guide($id);
        return $this->adminPage('guide-edit', 'guides', [
            'guide' => $guide,
            'errors' => $errors,
            'old' => $old + [
                'title' => (string) $guide['title'], 'summary' => (string) $guide['summary'], 'body' => (string) $guide['body'],
                'applies_to' => (string) ($guide['applies_to'] ?? ''), 'slug' => (string) $guide['slug'], 'note' => '',
            ],
            'appliesTo' => GuideWorkflow::APPLIES_TO,
            'maxBody' => $this->app->guideWorkflow()->maxBodyLength(),
            'slugLocked' => $guide['first_published_at'] !== null,
        ], ['title' => 'Edit: ' . $guide['title'], 'scripts' => ['js/guide-editor.js'], 'styles' => ['guides/css/styles.css']], $status);
    }

    public function update(string $id): Response
    {
        $admin = $this->admin('guides.moderate');
        $guide = $this->guide($id);
        $input = [
            'title' => $this->request->input('title'), 'summary' => $this->request->input('summary'),
            'body' => $this->request->input('body'), 'applies_to' => $this->request->input('applies_to'),
            'slug' => $this->request->input('slug'), 'note' => $this->request->input('note'),
        ];
        $expected = $this->expectedVersion();
        if ($expected === null || $expected !== (int) $guide['lock_version']) {
            return $this->edit($id, ['conflict' => 'The author or another administrator changed this guide while you were editing. Review the latest version before saving.'], $input, 409);
        }
        try {
            $workflow = $this->app->guideWorkflow();
            $workflow->adminEdit($admin, $guide, $workflow->clean($input), $input['slug'], $input['note'], $expected);
        } catch (ValidationException $error) {
            return $this->edit($id, $error->errors, $input, isset($error->errors['conflict']) ? 409 : 422);
        }
        $this->flash('success', 'Changes saved as a new revision. The published version changes only when you publish.');
        return $this->redirect('admin/guides/' . (int) $guide['id'] . '/');
    }

    public function action(string $id): Response
    {
        $admin = $this->admin('guides.moderate');
        $guide = $this->guide($id);
        $action = $this->request->input('action');
        if ($action === 'purge' && !$this->app->gate()->allows($admin, 'guides.purge')) {
            throw HttpException::forbidden('Only administrators can permanently delete guides.');
        }
        if (in_array($action, self::CONFIRM_REQUIRED, true) && $this->request->input('confirm') !== '1') {
            return $this->show($id, ['confirm' => 'Tick the confirmation box for “' . (GuideWorkflow::ADMIN_ACTIONS[$action] ?? $action) . '”.'], 422);
        }
        // The form carries the version the administrator reviewed; a stale decision is refused.
        $expected = $this->expectedVersion();
        if ($expected === null) {
            return $this->show($id, ['conflict' => 'This form is missing the guide version. Reload the page and try again.'], 409);
        }
        try {
            $result = $this->app->guideWorkflow()->adminAction($admin, $guide, $action,
                $this->request->input('note'), $this->request->input('confirmation'), $expected);
        } catch (ValidationException $error) {
            return $this->show($id, $error->errors, isset($error->errors['conflict']) ? 409 : 422);
        }
        if ($action === 'purge') {
            // Files are removed only after the deletion has committed.
            $this->app->media()->deleteRows($result['media']);
            $this->flash('success', $result['message']);
            return $this->redirect('admin/guides/?filter=deleted');
        }
        $this->flash('success', $result['message']);
        return $this->redirect('admin/guides/' . (int) $guide['id'] . '/');
    }

    private function expectedVersion(): ?int
    {
        $value = filter_var($this->request->input('lock_version'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : $value;
    }

    public function revision(string $id, string $revisionId): Response
    {
        $this->admin('guides.moderate');
        $guide = $this->guide($id);
        $repo = $this->app->guides();
        $revision = $repo->revision((int) $guide['id'], (int) $revisionId) ?? throw HttpException::notFound();
        $previous = $repo->previousRevision((int) $guide['id'], (int) $revision['revision_number']);
        $against = $this->request->query('against') === 'current' ? 'current' : 'previous';
        $base = $against === 'current' ? $revision : $previous;
        $target = $against === 'current' ? $guide : $revision;
        $diff = $base === null ? null : [
            'title' => $base['title'] === $target['title'] ? null : [$base['title'], $target['title']],
            'summary' => $base['summary'] === $target['summary'] ? null : [$base['summary'], $target['summary']],
            'body' => LineDiff::withContext(LineDiff::compare((string) $base['body'], (string) $target['body'])),
        ];
        return $this->adminPage('revision', 'guides', [
            'guide' => $guide,
            'revision' => $revision,
            'previous' => $previous,
            'against' => $against,
            'diff' => $diff,
        ], ['title' => 'Revision ' . $revision['revision_number'] . ': ' . $guide['title']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guide(string $id): array
    {
        return $this->app->guides()->find((int) $id) ?? throw HttpException::notFound();
    }
}
