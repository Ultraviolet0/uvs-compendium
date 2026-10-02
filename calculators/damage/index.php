<?php

declare(strict_types=1);

$page_title = "Damage Calculator | UV's Compendium";
$page_description = "Calculate Diablo and Hellfire physical attack and spell damage ranges with class stats, weapon modifiers, spell levels, and resistance cases.";
$base_path = '../../';
$current_page = 'damage';
$page_styles = ['calculators/css/styles.css', 'calculators/hellfire-damage/css/styles.css'];
$page_scripts = ['calculators/hellfire-damage/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
$calculator_breadcrumb_title = 'Damage Calculator';
require dirname(__DIR__) . '/breadcrumbs.php';
require dirname(__DIR__) . '/hellfire-damage/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
