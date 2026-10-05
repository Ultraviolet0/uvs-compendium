<?php

declare(strict_types=1);

namespace Uvs\Controller\Admin;

use Uvs\Admin\AuditLog;
use Uvs\Http\Response;

final class AuditController extends AdminController
{
    private const PER_PAGE = 50;

    public function index(): Response
    {
        $this->admin('audit.view');
        $action = $this->request->query('action');
        $action = isset(AuditLog::LABELS[$action]) ? $action : '';
        $type = in_array($this->request->query('type'), ['user', 'guide', 'setting', 'media', 'system'], true) ? $this->request->query('type') : '';
        $page = $this->request->queryInt('page');
        $result = $this->app->audit()->page($page, self::PER_PAGE, $action, $type);
        return $this->adminPage('audit', 'audit', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'action' => $action,
            'type' => $type,
            'labels' => AuditLog::LABELS,
        ], ['title' => 'Audit log']);
    }
}
