<?php
$page_title = "UV's Compendium | Tools & Guides";
$page_description = "A Diablo 1 and Hellfire compendium of calculators, mechanics references, and strategy guides with special attention to DevilutionX.";
$current_page = 'home';

require_once __DIR__ . '/includes/public_header.php';
?>

<section class="hero section-panel flow-lg" aria-labelledby="page-title">
  <p class="eyebrow">DevilutionX-focused Diablo I & Hellfire knowledge</p>
  <h1 id="page-title">UV's Compendium</h1>
  <p class="hero-copy">This site is a growing collection of tools, references, and strategy guides for players who want clearer access to the underlying mechanics of Diablo I & Hellfire in modern DevilutionX play.</p>
  <div class="button-row">
    <a class="button button-primary" href="calculators/">Explore Calculators</a>
    <!-- <a class="button button-secondary" href="guides/index.php">Read Guides</a> -->
  </div>
</section>

<section class="content-grid" aria-label="Site highlights">
  <article class="card flow">
    <p class="card-label">Reference</p>
    <h2>Documentation and references</h2>
    <p>Find official DevilutionX links, classic Diablo documents, hosted PDFs, and community resources for checking mechanics, version differences, and Hellfire-specific details.</p>
  </article>

  <article class="card flow">
    <p class="card-label">Calculators</p>
    <h2>Tools for the numbers</h2>
    <p>Use calculators for shopping, item pricing, affix ranges, qlvls, and other mechanics where Hellfire's hidden math is easier to work with when the formulas are handled for you.</p>
  </article>

  <article class="card flow">
    <p class="card-label">Guides</p>
    <h2>Practical play references</h2>
    <p>Read and watch focused guides for classes, shopping, quests, bosses, Hellfire additions, and DevilutionX-specific behavior, with an emphasis on useful details and practical implementation.</p>
  </article>
</section>

<section class="section-panel flow" aria-labelledby="mission-title">
  <p class="eyebrow">Project mission</p>
  <h2 id="mission-title">Preserve the knowledge. Improve the tooling.</h2>
  <p>Diablo I has decades of community knowledge behind it, but much of that information is spread across older guides, forum posts, and calculators built for a different era of the web. Other information has been completely lost to time as sites have gone down as people left the community. This site is intended to gather and preserve that knowledge into a clean, maintainable home that can grow naturally as new tools and guides are added.</p>
  <p>The primary focus is DevilutionX play which is the definitive way to play Diablo 1 and Hellfire in the modern era. I host references that make Diablo and Hellfire mechanics easier to understand, calculators that handle the tedious math, and guides that explain how to optimize your play and min-max your characters.</p>
</section>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
