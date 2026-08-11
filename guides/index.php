<?php
$page_title = "Guides | UV's Compendium";
$page_description = "Strategy guides and mechanics references for Diablo I, Hellfire, and DevilutionX.";
$base_path = '../';
$current_page = 'guides';

require_once __DIR__ . '/../includes/public_header.php';
?>

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
    <p>Max's walkthrough of Hellfire shopping mechanics, vendor behavior, and practical equipment targets.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="https://www.youtube.com/watch?v=c8MaZZezeMQ" target="_blank" rel="noopener noreferrer">Watch the Video</a></p>
  </article>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
