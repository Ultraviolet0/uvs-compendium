<?php

declare(strict_types=1);

$page_title = $page_title ?? "UV's Compendium | Tools & Guides";
$page_description = $page_description ?? "A Diablo 1 and Hellfire compendium of calculators, mechanics references, and strategy guides with special attention to DevilutionX.";
$base_path = $base_path ?? '';
$current_page = $current_page ?? '';
$page_styles = $page_styles ?? [];
$page_scripts = $page_scripts ?? [];

function h(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function site_url(string $path = ''): string
{
  global $base_path;

  if ($path === '') {
    return h($base_path !== '' ? $base_path : './');
  }

  return h($base_path . ltrim($path, '/'));
}

function asset_version(string $path): int
{
  $full_path = dirname(__DIR__) . '/' . ltrim($path, '/');

  return is_file($full_path) ? filemtime($full_path) : time();
}

$css_version = asset_version('css/styles.css');
$js_version = asset_version('js/scripts.js');

if (isset($page_style) && is_string($page_style) && $page_style !== '') {
  $page_styles[] = $page_style;
}

if (isset($page_script) && is_string($page_script) && $page_script !== '') {
  $page_scripts[] = $page_script;
}

$page_styles = array_values(array_unique(array_filter($page_styles, 'is_string')));
$page_scripts = array_values(array_unique(array_filter($page_scripts, 'is_string')));
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($page_description) ?>">
  <title><?= h($page_title) ?></title>

  <link rel="icon" href="<?php echo site_url('/favicon.ico'); ?>" sizes="any">
  <link rel="stylesheet" href="<?= site_url('css/styles.css') ?>?v=<?= $css_version ?>">
  <?php foreach ($page_styles as $stylesheet_path): ?>
    <link rel="stylesheet" href="<?= site_url($stylesheet_path) ?>?v=<?= asset_version($stylesheet_path) ?>">
  <?php endforeach; ?>
  <script src="<?= site_url('js/scripts.js') ?>?v=<?= $js_version ?>" defer></script>
  <?php foreach ($page_scripts as $script_path): ?>
    <script src="<?= site_url($script_path) ?>?v=<?= asset_version($script_path) ?>" defer></script>
  <?php endforeach; ?>
</head>

<body data-page="<?= h($current_page) ?>">
  <a class="skip-link" href="#main-content">Skip to main content</a>

  <div class="site-shell">
    <header class="site-sidebar" aria-label="Site header">
      <div class="mobile-header-bar">
        <div class="brand-block">
          <a class="brand-link" href="<?= site_url() ?>" aria-label="UV's Compendium home">
            <span class="brand-kicker">Diablo I & Hellfire</span>
            <span class="brand-title">UV's Compendium</span>
            <span class="brand-subtitle">Tools &amp; Guides</span>
          </a>
        </div>

        <button
          class="mobile-nav-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="site-navigation">
          <span class="mobile-nav-toggle-text">Menu</span>
          <span class="hamburger-icon" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </span>
        </button>
      </div>

      <nav id="site-navigation" class="site-nav" aria-label="Primary navigation">
        <section class="nav-section" aria-labelledby="nav-primary-heading">
          <p id="nav-primary-heading" class="nav-heading">Navigation</p>

          <ul class="nav-list">
            <li class="nav-item">
              <a class="nav-link nav-page-link" href="<?= site_url() ?>">Home</a>
            </li>

            <li class="nav-menu">
              <button class="nav-link nav-menu-toggle" type="button" aria-expanded="false" aria-controls="nav-reference-menu">
                <span>Reference</span>
                <span class="nav-arrow" aria-hidden="true"></span>
              </button>

              <ul id="nav-reference-menu" class="nav-submenu" aria-label="Reference links">
                <li><a class="nav-submenu-link" href="https://github.com/diasurgical/DevilutionX/blob/master/docs/installing.md" target="_blank" rel="noopener noreferrer">Install DevilutionX</a></li>
                <li><a class="nav-submenu-link" href="https://github.com/diasurgical/DevilutionX/releases" target="_blank" rel="noopener noreferrer">DevilutionX Releases</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('reference/jarulf162.pdf') ?>" target="_blank" rel="noopener noreferrer">Jarulf's Guide v1.62</a></li>
                <li><a class="nav-submenu-link" href="https://github.com/kphoenix137/JGX/blob/main/1-Introduction/1.0.md" target="_blank" rel="noopener noreferrer">Jarulf's Guide X</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('reference/hellfire-shopping-differences.pdf') ?>" target="_blank" rel="noopener noreferrer">Max's Hellfire Shopping Differences</a></li>
                <li><a class="nav-submenu-link" href="https://docs.google.com/spreadsheets/d/1oZnXf1MbXWDGosbR6votB0E7V6Si1dUp" target="_blank" rel="noopener noreferrer">Repair Durability Loss Sheet</a></li>
                <li><a class="nav-submenu-link" href="https://devilutionx.com/xpchart" target="_blank" rel="noopener noreferrer">Multiplayer Experience Chart</a></li>
              </ul>
            </li>

            <li class="nav-menu">
              <button
                class="nav-link nav-menu-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="nav-calculators-menu">
                <span>Calculators</span>
                <span class="nav-arrow" aria-hidden="true"></span>
              </button>

              <ul id="nav-calculators-menu" class="nav-submenu" aria-label="Calculator links">
                <li><a class="nav-submenu-link" href="<?= site_url('calculators/') ?>">All Calculators</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('calculators/premium-item-checker/') ?>">Hellfire Premium Item Checker</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('calculators/hellfire-item-price/') ?>">Hellfire Item Price Calculator</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('calculators/shop-qlvl/') ?>">Hellfire Shop Qlvl Calculator</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('calculators/warrior-repair/') ?>">Warrior Repair Calculator</a></li>
                <li><a class="nav-submenu-link" href="<?= site_url('calculators/hellfire-damage/') ?>">Hellfire Damage Calculator</a></li>
              </ul>
            </li>

            <li class="nav-menu">
              <button
                class="nav-link nav-menu-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="nav-guides-menu">
                <span>Guides</span>
                <span class="nav-arrow" aria-hidden="true"></span>
              </button>

              <ul id="nav-guides-menu" class="nav-submenu" aria-label="Guide links">
                <li><a class="nav-submenu-link" href="https://www.youtube.com/watch?v=c8MaZZezeMQ" target="_blank" rel="noopener noreferrer">Max's Hellfire Shopping Guide</a></li>
                <!-- <li><a class="nav-submenu-link" href="<?= site_url('guides/index.php') ?>">Guides Home</a></li>
                  <li><a class="nav-submenu-link" href="<?= site_url('guides/shopping.php') ?>">Shopping &amp; Affixes</a></li>
                  <li><a class="nav-submenu-link" href="#">Bard Guide Placeholder</a></li>
                  <li><a class="nav-submenu-link" href="#">Warrior Guide Placeholder</a></li> -->
              </ul>
            </li>
          </ul>
        </section>
      </nav>
    </header>

    <main id="main-content" class="site-main">
