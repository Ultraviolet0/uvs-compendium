<?php

declare(strict_types=1);

$page_title = "Warrior Repair Calculator | UV's Compendium";
$page_description = "Plan deterministic warrior repair durability-loss paths for Diablo I and DevilutionX items.";
$base_path = '../../';
$current_page = 'warrior-repair-calculator';
$page_styles = ['calculators/css/styles.css', 'calculators/warrior-repair/css/styles.css'];
$page_scripts = ['calculators/warrior-repair/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
require __DIR__ . '/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
