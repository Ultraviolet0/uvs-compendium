<?php

declare(strict_types=1);

$page_title = "Calculators | UV's Compendium";
$page_description = "Diablo I, Hellfire, and DevilutionX calculator tools for item prices, shopping qlvls, premium items, repair durability, physical damage, spell damage, and related mechanics.";
$base_path = '../';
$current_page = 'calculators';
$page_styles = [
  'css/in-page-navigation.css',
  'calculators/css/styles.css',
  'calculators/premium-item-checker/css/styles.css',
  'calculators/hellfire-item-price/css/styles.css',
  'calculators/shop-qlvl/css/styles.css',
  'calculators/warrior-repair/css/styles.css',
  'calculators/hellfire-damage/css/styles.css',
];
$page_scripts = [
  'js/in-page-navigation.js',
  'calculators/hellfire-item-price/js/scripts.js',
  'calculators/warrior-repair/js/scripts.js',
  'calculators/hellfire-damage/js/scripts.js',
];

require_once dirname(__DIR__) . '/includes/public_header.php';
$calculator_breadcrumb_title = 'Calculators';
require __DIR__ . '/breadcrumbs.php';
?>

<div class="calculator-collection-layout">
  <aside class="in-page-nav-column" aria-label="Calculator navigation">
    <details class="in-page-nav-panel" open>
      <summary class="in-page-nav-summary">Calculators</summary>
      <nav
        class="in-page-nav"
        aria-label="Calculator list"
        data-in-page-nav
        data-in-page-nav-target="calculator-collection"
        data-in-page-nav-selector=".calculator-page > h2, .calculator-page > header > h2"
        data-in-page-nav-collapse-width="1120">
        <p class="in-page-nav-placeholder">Calculator links appear here automatically.</p>
      </nav>
    </details>
  </aside>

  <div id="calculator-collection" class="calculator-collection-content">
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

    $hellfire_damage_heading_level = 'h2';
    require __DIR__ . '/hellfire-damage/calculator.php';
    ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/public_footer.php';
