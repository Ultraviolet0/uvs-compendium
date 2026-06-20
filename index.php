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
    <a class="button button-primary" href="calculators.php">Explore Calculators</a>
    <a class="button button-secondary" href="guides/index.php">Read Guides</a>
  </div>
</section>

<section class="content-grid" aria-label="Site highlights">
  <article class="card flow">
    <p class="card-label">Calculators</p>
    <h2>Mechanics made usable</h2>
    <p>Plan character levels, shopping outcomes, affix ranges, item generation, and other calculation-heavy systems without manually cross-checking scattered tables.</p>
  </article>

  <article class="card flow">
    <p class="card-label">Guides</p>
    <h2>Strategy with context</h2>
    <p>
      Build references for classes, quests, bosses, shopping, Hellfire additions, and DevilutionX-specific behavior in a format that is easy to expand over time.
    </p>
  </article>

  <article class="card flow">
    <p class="card-label">Reference</p>
    <h2>Old-school spirit, modern code</h2>
    <p>
      The design keeps the dark Diablo compendium feel while using semantic HTML, responsive layout, accessible navigation, and reusable CSS utilities.
    </p>
  </article>
</section>

<section class="section-panel flow" aria-labelledby="mission-title">
  <p class="eyebrow">Project mission</p>
  <h2 id="mission-title">Preserve the knowledge. Improve the tooling.</h2>
  <p>
    Diablo I has decades of community knowledge behind it, but much of that information is spread across older guides, forum posts, and calculators built for a different era of the web. This site is intended to gather that knowledge into a clean, maintainable home that can grow naturally as new tools and guides are added.
  </p>
  <p>
    The first focus is practical DevilutionX-era play: calculators that handle the tedious math, guides that explain the rules behind the results, and references that make Diablo and Hellfire mechanics easier to understand without losing the atmosphere of the original game.
  </p>
</section>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
