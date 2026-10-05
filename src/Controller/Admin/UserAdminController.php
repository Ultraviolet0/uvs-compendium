<?php

declare(strict_types=1);

namespace Uvs\Controller\Admin;

use Uvs\Http\HttpException;
use Uvs\Http\Response;
use Uvs\Support\ValidationException;
use Uvs\Users\AccountService;
use Uvs\Users\UserRepository;

final class UserAdminController extends AdminController
{
    private const PER_PAGE = 25;
    private const CONFIRM_REQUIRED = ['reject', 'suspend'];

    public function index(): Response
    {
        $this->admin('users.manage');
        $query = mb_substr(trim($this->request->query('q')), 0, 100);
        $status = $this->request->query('status');
        $role = $this->request->query('role');
        $page = $this->request->queryInt('page');
        $result = $this->app->users()->search($query, $status ?: null, $role ?: null, $page, self::PER_PAGE);
        return $this->adminPage('users', 'users', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'query' => $query,
            'status' => in_array($status, UserRepository::STATUSES, true) ? $status : '',
            'role' => in_array($role, UserRepository::ROLES, true) ? $role : '',
            'counts' => $this->app->users()->statusCounts(),
        ], ['title' => 'Users']);
    }

    public function show(string $id, array $errors = [], int $status = 200): Response
    {
        $admin = $this->admin('users.manage');
        $user = $this->app->users()->findWithProfile((int) $id) ?? throw HttpException::notFound();
        $db = $this->app->db();
        $guides = $db->all('SELECT id, title, slug, review_status, visibility, deleted_at, first_published_at, updated_at
            FROM guides WHERE author_id = :id ORDER BY updated_at DESC LIMIT 50', ['id' => (int) $user['id']]);
        $mediaBytes = $this->app->media()->usage((int) $user['id']);
        return $this->adminPage('user', 'users', [
            'admin' => $admin,
            'member' => $user,
            'guides' => $guides,
            'mediaBytes' => $mediaBytes,
            'characters' => (int) $db->value('SELECT COUNT(*) FROM user_characters WHERE user_id = :id', ['id' => (int) $user['id']]),
            'history' => $this->app->audit()->forTarget('user', (int) $user['id']),
            'actions' => $this->availableActions($user),
            'errors' => $errors,
            'isSelf' => (int) $admin['id'] === (int) $user['id'],
        ], ['title' => 'User: ' . $user['username']], $status);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<string>
     */
    private function availableActions(array $user): array
    {
        $actions = [];
        foreach (AccountService::STATUS_ACTIONS as $action => [$from]) {
            if (in_array($user['status'], $from, true)) {
                $actions[] = $action;
            }
        }
        return $actions;
    }

    public function status(string $id): Response
    {
        $admin = $this->admin('users.manage');
        $action = $this->request->input('action');
        if (in_array($action, self::CONFIRM_REQUIRED, true) && $this->request->input('confirm') !== '1') {
            return $this->show($id, ['confirm' => 'Tick the confirmation box to ' . $action . ' this account.'], 422);
        }
        try {
            $this->app->accounts()->changeStatus($admin, (int) $id, $action, $this->request->input('reason'));
        } catch (ValidationException $error) {
            return $this->show($id, $error->errors, 422);
        }
        $user = $this->app->users()->find((int) $id);
        if ($user !== null && $action === 'approve' && $this->app->mailer()->isEnabled()) {
            $this->app->mailer()->send((string) $user['email'], "Your UV's Compendium account is approved",
                "Hello {$user['username']},\n\nYour account has been approved. You can now write and submit guides.\n");
        }
        $labels = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'reactivate' => 'reactivated'];
        $this->flash('success', ($user['username'] ?? 'The account') . ' was ' . ($labels[$action] ?? 'updated') . '.');
        return $this->redirect($this->request->input('return') === 'list' ? 'admin/users/?status=pending' : 'admin/users/' . (int) $id . '/');
    }

    public function role(string $id): Response
    {
        $admin = $this->admin('users.manage');
        if ($this->request->input('confirm') !== '1') {
            return $this->show($id, ['confirm' => 'Tick the confirmation box to change this role.'], 422);
        }
        try {
            $this->app->accounts()->changeRole($admin, (int) $id, $this->request->input('role'));
        } catch (ValidationException $error) {
            return $this->show($id, $error->errors, 422);
        }
        $this->flash('success', 'Role updated.');
        return $this->redirect('admin/users/' . (int) $id . '/');
    }

    public function note(string $id): Response
    {
        $admin = $this->admin('users.manage');
        try {
            $this->app->accounts()->updateAdminNote($admin, (int) $id, $this->request->input('admin_note'));
        } catch (ValidationException $error) {
            return $this->show($id, $error->errors, 422);
        }
        $this->flash('success', 'Note saved.');
        return $this->redirect('admin/users/' . (int) $id . '/');
    }
}
