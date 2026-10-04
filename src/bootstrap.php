<?php

declare(strict_types=1);

/*
 * Shared bootstrap for the front controller, CLI, and shared page header.
 * Loading this file has no side effects beyond autoloading and default settings.
 */

$uvsRoot = dirname(__DIR__);
$autoload = $uvsRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Dependencies are not installed.');
}
require_once $autoload;
require_once $uvsRoot . '/includes/helpers.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

return Uvs\App::boot($uvsRoot);
