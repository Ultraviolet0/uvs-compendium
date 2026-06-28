<?php

declare(strict_types=1);

$page_title = "Hellfire Premium Item Checker | UV's Compendium";
$page_description = "Check Hellfire premium item combinations, affix data, source availability, and detailed price ranges.";
$base_path = '../../';
$current_page = 'premium-item-checker';
$page_styles = ['calculators/css/styles.css', 'calculators/premium-item-checker/css/styles.css'];
$page_scripts = ['calculators/premium-item-checker/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
require __DIR__ . '/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
