<?php
$page_title = "Guides | UV's Compendium";
$page_description = "Strategy guides and mechanics references for Diablo I, Hellfire, and DevilutionX.";
$base_path = '../';
$current_page = 'guides';
$page_styles = ['guides/css/styles.css'];

require_once __DIR__ . '/../includes/public_header.php';
?>

<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <li aria-current="page">Guides</li>
  </ol>
</nav>

<section class="section-panel flow-lg" aria-labelledby="page-title">
  <p class="eyebrow">Documentation</p>
  <h1 id="page-title">Strategy Guides</h1>
  <p class="hero-copy">
    Browse practical Diablo I, Hellfire, and DevilutionX guides from UV's Compendium and the wider community.
  </p>
</section>

<section class="content-grid guide-catalog" aria-label="Guide catalog">
  <article class="card flow">
    <p class="card-label">Strategy Guide</p>
    <h2>Fast Character Development</h2>
    <p>A practical Diablo I and Hellfire route for leveling, gearing, spell development, shrine hunting, and efficient farming.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="<?= site_url('guides/fast-character-development/') ?>">Read the Guide</a></p>
  </article>

  <article class="card flow">
    <p class="card-label">Strategy Guide</p>
    <h2>UV's Shopping &amp; Affixes</h2>
    <p>A detailed guide to vendor mechanics, shopper levels, item-pool manipulation, price anchors, and targeted equipment shopping.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="<?= site_url('guides/shopping/') ?>">Read the Guide</a></p>
  </article>

  <article class="card flow">
    <p class="card-label">Video Guide</p>
    <h2>Max's Hellfire Shopping Video</h2>
    <p>Max's practical Hellfire shopping demonstration, using a King's Bastard Sword of Speed or Haste as the target.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="<?= site_url('guides/max-shopping-video/') ?>">Watch the Video</a></p>
  </article>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
