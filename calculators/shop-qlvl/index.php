<?php

declare(strict_types=1);

$page_title = "Hellfire Shop Qlvl Calculator | UV's Compendium";
$page_description = "Calculate Hellfire shop item and affix qlvl ranges for Griswold, Wirt, and Adria.";
$base_path = '../../';
$current_page = 'shop-qlvl';
$page_styles = ['calculators/css/styles.css', 'calculators/shop-qlvl/css/styles.css'];
$page_scripts = ['calculators/shop-qlvl/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
require __DIR__ . '/calculator.php';
require_once dirname(__DIR__, 2) . '/includes/public_footer.php';
