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
    This section will collect practical Diablo I, Hellfire, and DevilutionX guides as the compendium grows.
  </p>
</section>

<section class="content-grid" aria-label="Guide placeholders">
  <article class="card flow">
    <p class="card-label">Guide</p>
    <h2>Shopping &amp; Affixes</h2>
    <p>A home for shopping strategy, affix rules, and item-generation notes.</p>
    <p><a class="button button-secondary" href="<?= site_url('guides/shopping.php') ?>">Open Guide Placeholder</a></p>
  </article>

  <article class="card flow">
    <p class="card-label">Planned Guide</p>
    <h2>Bard</h2>
    <p>Placeholder for Hellfire Bard strategy and mechanics.</p>
  </article>

  <article class="card flow">
    <p class="card-label">Planned Guide</p>
    <h2>Warrior</h2>
    <p>Placeholder for Warrior strategy, gearing, and progression notes.</p>
  </article>
</section>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
