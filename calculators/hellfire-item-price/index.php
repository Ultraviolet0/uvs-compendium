<?php

declare(strict_types=1);

$page_title = "Hellfire Item Price Calculator | UV's Compendium";
$page_description = "Calculate Diablo: Hellfire item buy and resale prices for base, magic, staff, jewelry, and unique items.";
$base_path = '../../';
$current_page = 'hellfire-item-price-calculator';
$page_styles = ['calculators/css/styles.css', 'calculators/hellfire-item-price/css/styles.css'];
$page_scripts = ['calculators/hellfire-item-price/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
require __DIR__ . '/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
