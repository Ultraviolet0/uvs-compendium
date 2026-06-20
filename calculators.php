<?php
$page_title = "Calculators | UV's Compendium";
$page_description = "Diablo I, Hellfire, and DevilutionX calculator tools for item generation, shopping, affixes, and related mechanics.";
$current_page = 'calculators';

require_once __DIR__ . '/includes/public_header.php';
?>

<section class="section-panel flow-lg" aria-labelledby="page-title">
  <p class="eyebrow">Tools</p>
  <h1 id="page-title">Calculators</h1>
  <p class="hero-copy">
    This section will collect Diablo I, Hellfire, and DevilutionX calculators for mechanics that are easier to understand when the tedious math is handled automatically.
  </p>
</section>

<section class="content-grid" aria-label="Calculator placeholders">
  <article class="card flow">
    <p class="card-label">Planned Tool</p>
    <h2>Item Price Calculator</h2>
    <p>Estimate item prices and related shop behavior using modern JavaScript while keeping the page structure maintainable through PHP includes.</p>
  </article>

  <article class="card flow">
    <p class="card-label">Planned Tool</p>
    <h2>Shop Qlvl Calculator</h2>
    <p>Check shopping outcomes, qlvl requirements, and vendor-related mechanics without manually cross-referencing tables.</p>
  </article>

  <article class="card flow">
    <p class="card-label">Planned Tool</p>
    <h2>Affix Calculator</h2>
    <p>Explore affix ranges, item eligibility, and generation rules for Diablo and Hellfire equipment.</p>
  </article>
</section>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
