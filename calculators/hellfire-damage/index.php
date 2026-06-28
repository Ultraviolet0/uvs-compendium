<?php

declare(strict_types=1);

$page_title = "Hellfire Damage Calculator | UV's Compendium";
$page_description = "Calculate Hellfire physical attack damage and direct spell damage ranges with class stat presets, weapon modifiers, spell levels, and resistance cases.";
$base_path = '../../';
$current_page = 'hellfire-damage';
$page_styles = ['calculators/css/styles.css', 'calculators/hellfire-damage/css/styles.css'];
$page_scripts = ['calculators/hellfire-damage/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
require __DIR__ . '/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
