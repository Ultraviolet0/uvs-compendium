<?php

declare(strict_types=1);

$page_title = "Calculators | UV's Compendium";
$page_description = "Diablo I, Hellfire, and DevilutionX calculator tools for item prices, shopping qlvls, affixes, and related mechanics.";
$base_path = '../';
$current_page = 'calculators';
$page_styles = [
  'calculators/css/styles.css',
  'calculators/premium-item-checker/css/styles.css',
  'calculators/hellfire-item-price/css/styles.css',
  'calculators/shop-qlvl/css/styles.css',
  'calculators/warrior-repair/css/styles.css',
];
$page_scripts = [
  'calculators/premium-item-checker/js/scripts.js',
  'calculators/hellfire-item-price/js/scripts.js',
  'calculators/shop-qlvl/js/scripts.js',
  'calculators/warrior-repair/js/scripts.js',
];

require_once dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <p class="eyebrow">Tools</p>
  <h1 id="page-title">Calculators</h1>
  <p class="hero-copy">This page holds the entire collection of Diablo I and Hellfire calculator tools hosted on this site.</p>
</section>

<?php
$premium_item_checker_heading_level = 'h2';
require __DIR__ . '/premium-item-checker/calculator.php';

$hellfire_item_price_heading_level = 'h2';
require __DIR__ . '/hellfire-item-price/calculator.php';

$shop_qlvl_heading_level = 'h2';
require __DIR__ . '/shop-qlvl/calculator.php';

$warrior_repair_heading_level = 'h2';
require __DIR__ . '/warrior-repair/calculator.php';

require_once dirname(__DIR__) . '/includes/public_footer.php';
