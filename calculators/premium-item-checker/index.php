<?php

declare(strict_types=1);

$page_title = "Premium Item Checker | UV's Compendium";
$page_description = "Check Diablo and Hellfire premium item combinations, affixes, source availability, and prices in single player or multiplayer.";
$base_path = '../../';
$current_page = 'premium-item-checker';
$page_styles = ['calculators/css/styles.css', 'calculators/premium-item-checker/css/styles.css'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
$calculator_breadcrumb_title = 'Premium Item Checker';
require dirname(__DIR__) . '/breadcrumbs.php';
require __DIR__ . '/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
