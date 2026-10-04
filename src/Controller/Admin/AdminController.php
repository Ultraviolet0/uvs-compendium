<?php

declare(strict_types=1);

namespace Uvs\Controller\Admin;

use Uvs\Controller\Controller;
use Uvs\Http\HttpException;

abstract class AdminController extends Controller
{
    /**
     * Anonymous visitors are sent to sign in; signed-in accounts without the
     * administrative role, or without the specific permission, receive 403.
     *
     * @return array<string, mixed>
     */
    protected function admin(string $ability = 'admin.access'): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new HttpException(401, 'Sign in required');
        }
        $gate = $this->app->gate();
        if (!$gate->allows($user, 'admin.access')) {
            throw HttpException::forbidden('This area is for administrators.');
        }
        if (!$gate->allows($user, $ability)) {
            throw HttpException::forbidden('Your role does not include this administrative permission.');
        }
        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $page
     */
    protected function adminPage(string $template, string $section, array $data, array $page = [], int $status = 200): \Uvs\Http\Response
    {
        return $this->render('admin/' . $template, $data + ['section' => $section], $page + ['current' => 'admin'], $status);
    }
}
