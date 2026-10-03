<?php

declare(strict_types=1);

namespace Uvs\Controller\Admin;

use Uvs\Http\Response;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $user = $this->admin();
        $gate = $this->app->gate();
        $users = $gate->allows($user, 'users.manage') ? $this->app->users()->statusCounts() : null;
        $guides = $this->app->guides()->adminCounts();
        $queue = $this->app->guides()->adminList('queue', '', 1, 5)['rows'];
        $pending = $users !== null ? $this->app->users()->search('', 'pending', null, 1, 5)['rows'] : [];
        $audit = $gate->allows($user, 'audit.view') ? $this->app->audit()->page(1, 8)['rows'] : [];
        return $this->adminPage('dashboard', 'dashboard', [
            'user' => $user,
            'userCounts' => $users,
            'guideCounts' => $guides,
            'queue' => $queue,
            'pendingUsers' => $pending,
            'audit' => $audit,
            'settings' => $this->app->settings()->all(),
            'turnstile' => $this->app->turnstile()->status(),
            'mail' => $this->app->mailer()->transport(),
        ], ['title' => 'Administration']);
    }
}
