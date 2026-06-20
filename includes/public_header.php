<?php
declare(strict_types=1);

const SITE_VERSION = '0.07';

$page_title = $page_title ?? "UV's Compendium | Tools & Guides";
$page_description = $page_description ?? "A Diablo 1 and Hellfire compendium of calculators, mechanics references, and strategy guides with special attention to DevilutionX.";
$base_path = $base_path ?? '';
$current_page = $current_page ?? '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function site_url(string $path = ''): string
{
    global $base_path;

    return h($base_path . ltrim($path, '/'));
}

function asset_version(string $path): int
{
    $full_path = dirname(__DIR__) . '/' . ltrim($path, '/');

    return is_file($full_path) ? filemtime($full_path) : time();
}

$css_version = asset_version('css/styles.css');
$js_version = asset_version('js/scripts.js');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($page_description) ?>">
    <meta name="generator" content="UV's Compendium <?= SITE_VERSION ?>">
    <title><?= h($page_title) ?></title>

    <link rel="stylesheet" href="<?= site_url('css/styles.css') ?>?v=<?= $css_version ?>">
    <script src="<?= site_url('js/scripts.js') ?>?v=<?= $js_version ?>" defer></script>
  </head>

  <body data-page="<?= h($current_page) ?>">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <div class="site-shell">
      <header class="site-sidebar" aria-label="Site header">
        <div class="brand-block">
          <a class="brand-link" href="<?= site_url('index.php') ?>" aria-label="Diablo Compendium home">
            <span class="brand-kicker">Diablo I & Hellfire</span>
            <span class="brand-title">UV's Compendium</span>
            <span class="brand-subtitle">Tools &amp; Guides</span>
          </a>
        </div>

        <nav class="site-nav" aria-label="Primary navigation">
          <section class="nav-section" aria-labelledby="nav-main-heading">
            <p id="nav-main-heading" class="nav-heading">Main</p>

            <ul class="nav-list">
              <li class="nav-menu">
                <button
                  class="nav-link nav-menu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="nav-home-menu"
                >
                  <span>Home</span>
                  <span class="nav-arrow" aria-hidden="true"></span>
                </button>

                <ul id="nav-home-menu" class="nav-submenu" aria-label="Home links">
                  <li><a class="nav-submenu-link" href="<?= site_url('index.php') ?>">Overview</a></li>
                  <li><a class="nav-submenu-link" href="<?= site_url('index.php#mission-title') ?>">Project Mission</a></li>
                  <li><a class="nav-submenu-link" href="#">Updates Placeholder</a></li>
                </ul>
              </li>

              <li class="nav-menu">
                <button
                  class="nav-link nav-menu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="nav-calculators-menu"
                >
                  <span>Calculators</span>
                  <span class="nav-arrow" aria-hidden="true"></span>
                </button>

                <ul id="nav-calculators-menu" class="nav-submenu" aria-label="Calculator links">
                  <li><a class="nav-submenu-link" href="<?= site_url('calculators.php') ?>">Calculators Home</a></li>
                  <li><a class="nav-submenu-link" href="#">Item Price Calculator</a></li>
                  <li><a class="nav-submenu-link" href="#">Shop Qlvl Calculator</a></li>
                  <li><a class="nav-submenu-link" href="#">Affix Calculator</a></li>
                </ul>
              </li>
            </ul>
          </section>

          <section class="nav-section" aria-labelledby="nav-docs-heading">
            <p id="nav-docs-heading" class="nav-heading">Documentation</p>

            <ul class="nav-list">
              <li class="nav-menu">
                <button
                  class="nav-link nav-menu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="nav-devilutionx-menu"
                >
                  <span>DevilutionX</span>
                  <span class="nav-arrow" aria-hidden="true"></span>
                </button>

                <ul id="nav-devilutionx-menu" class="nav-submenu" aria-label="DevilutionX links">
                  <li><a class="nav-submenu-link" href="https://github.com/diasurgical/DevilutionX/blob/master/docs/installing.md" target="_blank" rel="noopener noreferrer">Install DevilutionX</a></li>
                  <li><a class="nav-submenu-link" href="https://github.com/diasurgical/DevilutionX/releases" target="_blank" rel="noopener noreferrer">Release Notes</a></li>
                  <li><a class="nav-submenu-link" href="#">DevilutionX Mechanics Placeholder</a></li>
                </ul>
              </li>

              <li class="nav-menu">
                <button
                  class="nav-link nav-menu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="nav-diablo-knowledge-menu"
                >
                  <span>Diablo Knowledge</span>
                  <span class="nav-arrow" aria-hidden="true"></span>
                </button>

                <ul id="nav-diablo-knowledge-menu" class="nav-submenu" aria-label="Diablo knowledge links">
                  <li><a class="nav-submenu-link" href="#">Base Game Mechanics</a></li>
                  <li><a class="nav-submenu-link" href="#">Classes Placeholder</a></li>
                  <li><a class="nav-submenu-link" href="#">Item Generation Placeholder</a></li>
                </ul>
              </li>

              <li class="nav-menu">
                <button
                  class="nav-link nav-menu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="nav-hellfire-menu"
                >
                  <span>Hellfire</span>
                  <span class="nav-arrow" aria-hidden="true"></span>
                </button>

                <ul id="nav-hellfire-menu" class="nav-submenu" aria-label="Hellfire links">
                  <li><a class="nav-submenu-link" href="<?= site_url('assets/hellfire-shopping-differences.pdf') ?>" target="_blank" rel="noopener noreferrer">Max's Hellfire Shopping Differences</a></li>
                  <li><a class="nav-submenu-link" href="https://www.youtube.com/watch?v=c8MaZZezeMQ" target="_blank" rel="noopener noreferrer">Max's Hellfire Shopping Video</a></li>
                  <li><a class="nav-submenu-link" href="#">Bard &amp; Barbarian Placeholder</a></li>
                </ul>
              </li>

              <li class="nav-menu">
                <button
                  class="nav-link nav-menu-toggle"
                  type="button"
                  aria-expanded="false"
                  aria-controls="nav-guides-menu"
                >
                  <span>Strategy Guides</span>
                  <span class="nav-arrow" aria-hidden="true"></span>
                </button>

                <ul id="nav-guides-menu" class="nav-submenu" aria-label="Strategy guide links">
                  <li><a class="nav-submenu-link" href="<?= site_url('guides/index.php') ?>">Guides Home</a></li>
                  <li><a class="nav-submenu-link" href="<?= site_url('guides/shopping.php') ?>">Shopping &amp; Affixes</a></li>
                  <li><a class="nav-submenu-link" href="#">Bard Guide Placeholder</a></li>
                  <li><a class="nav-submenu-link" href="#">Warrior Guide Placeholder</a></li>
                </ul>
              </li>
            </ul>
          </section>
        </nav>
      </header>

      <main id="main-content" class="site-main">
