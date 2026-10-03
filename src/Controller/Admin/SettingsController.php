<?php

declare(strict_types=1);

namespace Uvs\Controller\Admin;

use InvalidArgumentException;
use Uvs\Http\Response;

final class SettingsController extends AdminController
{
    public function show(array $errors = [], int $status = 200): Response
    {
        $this->admin('settings.manage');
        $settings = $this->app->settings();
        $config = $this->app->config;
        return $this->adminPage('settings', 'settings', [
            'definitions' => $settings->definitions(),
            'values' => $settings->all(),
            'errors' => $errors,
            'system' => [
                'Environment' => $config->env(),
                'Cloudflare Turnstile' => match ($this->app->turnstile()->status()) {
                    'enabled' => 'Enabled (site key and secret configured privately)',
                    'test' => 'Test mode (offline verifier; development only)',
                    'disabled' => 'Disabled (development only)',
                    default => 'Not configured — signup and password reset are unavailable',
                },
                'Outgoing email' => match ($this->app->mailer()->transport()) {
                    'smtp' => 'SMTP configured',
                    'log' => 'Captured to private storage (development only)',
                    default => 'Not configured — password reset email is unavailable',
                },
                'PHP upload limit' => (string) ini_get('upload_max_filesize'),
                'Image storage' => is_writable($this->app->storagePath()) ? 'Writable' : 'Not writable',
            ],
        ], ['title' => 'Settings'], $status);
    }

    public function update(): Response
    {
        $admin = $this->admin('settings.manage');
        $settings = $this->app->settings();
        $errors = [];
        $changes = [];
        foreach ($settings->definitions() as $name => $definition) {
            if (!empty($definition['scaffold'])) {
                continue;
            }
            $value = $definition['type'] === 'bool' ? ($this->request->input($name) === '1' ? '1' : '0') : $this->request->input($name);
            try {
                $current = $settings->get($name);
                $normalized = $definition['type'] === 'bool' ? $value === '1' : (int) $value;
                if ($definition['type'] === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new InvalidArgumentException("{$definition['label']} must be a whole number.");
                }
                if ($normalized === $current) {
                    continue;
                }
                $previous = $settings->set($name, $value, (int) $admin['id']);
                $changes[$name] = [$previous, $settings->get($name)];
            } catch (InvalidArgumentException $error) {
                $errors[$name] = $error->getMessage();
            }
        }
        foreach ($changes as $name => [$from, $to]) {
            $this->app->audit()->record($admin, 'setting.changed', 'setting', null, $name, [
                'setting' => $name, 'from' => is_bool($from) ? ($from ? 'on' : 'off') : $from, 'to' => is_bool($to) ? ($to ? 'on' : 'off') : $to,
            ]);
        }
        if ($errors !== []) {
            return $this->show($errors, 422);
        }
        $this->flash('success', $changes === [] ? 'No settings changed.' : 'Settings saved.');
        return $this->redirect('admin/settings/');
    }
}
