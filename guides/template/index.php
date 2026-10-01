<?php

declare(strict_types=1);

/**
 * Reusable guide-entry template.
 *
 * Copy this file for a new guide, update the page metadata and article content,
 * and keep section headings inside #guide-content. The in-page navigation script
 * automatically includes h2 and h3 headings, assigns missing IDs, and highlights
 * the reader's current section. Add data-in-page-nav-ignore to any heading that
 * should not appear in the table of contents.
 */

$page_title = "Guide Entry Template | UV's Compendium";
$page_description = "A reusable Diablo I and Hellfire guide-entry template for UV's Compendium.";
$base_path = '../../';
$current_page = 'guide-template';
$page_styles = ['css/in-page-navigation.css', 'guides/css/styles.css'];
$page_scripts = ['js/in-page-navigation.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
?>

<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <li><a href="<?= site_url('guides/') ?>">Guides</a></li>
    <li aria-current="page">Guide Entry Template</li>
  </ol>
</nav>

<div class="guide-layout">
  <aside class="in-page-nav-column" aria-label="Page sections">
    <details class="in-page-nav-panel" open>
      <summary class="in-page-nav-summary">On this page</summary>
      <nav
        class="in-page-nav"
        aria-label="Table of contents"
        data-in-page-nav
        data-in-page-nav-target="guide-content"
        data-in-page-nav-selector="h2, h3"
        data-in-page-nav-collapse-width="1120">
        <p class="in-page-nav-placeholder">Sections appear here automatically.</p>
      </nav>
    </details>
  </aside>

  <article class="guide-entry">
    <header class="guide-header">
      <p class="eyebrow">Strategy Guide Template</p>
      <h1 class="guide-title">The Wanderer's Handbook to Dungeon Survival</h1>
      <p class="guide-deck">A bare-bones prototype demonstrating the reusable layouts, components, and typography available to Diablo I and Hellfire guide entries.</p>

      <dl class="guide-meta">
        <div class="guide-meta-item">
          <dt>Written by</dt>
          <dd>Ultraviolet</dd>
        </div>
        <div class="guide-meta-item">
          <dt>Published</dt>
          <dd><time datetime="2026-08-10">August 10, 2026</time></dd>
        </div>
        <div class="guide-meta-item">
          <dt>Updated</dt>
          <dd><time datetime="2026-08-10">August 10, 2026</time></dd>
        </div>
        <div class="guide-meta-item">
          <dt>Applies to</dt>
          <dd>Hellfire 1.01 / DevilutionX</dd>
        </div>
      </dl>
    </header>

    <figure class="guide-figure guide-featured-figure">
      <div class="guide-image-placeholder" role="img" aria-label="Placeholder for a Diablo guide featured image">
        <span>Featured guide image</span>
        <small>Recommended ratio: 16:9</small>
      </div>
      <figcaption class="guide-figcaption">Replace this placeholder with an image using the <code>guide-image</code> class and write a useful caption here.</figcaption>
    </figure>

    <div id="guide-content" class="guide-content">
      <p class="guide-lead">Lorem ipsum dolor sit amet, consectetur adipiscing elit. This introductory paragraph uses the larger lead style and should quickly explain what the guide covers, who it is for, and why the information matters.</p>

      <aside class="guide-callout guide-callout-note" aria-label="Guide summary">
        <p class="guide-callout-title">At a glance</p>
        <p>Use this component for a concise summary, important version note, source clarification, or other information readers should see before continuing.</p>
      </aside>

      <section class="guide-section">
        <h2>Preparing for the Descent</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas vitae lacus nec arcu ullamcorper posuere. Integer placerat, lorem quis finibus interdum, velit erat faucibus sapien, vitae consequat risus est non risus.</p>

        <dl class="guide-quick-facts">
          <div class="guide-quick-fact">
            <dt class="guide-quick-fact-label">Recommended Level</dt>
            <dd><strong>20+</strong></dd>
          </div>
          <div class="guide-quick-fact">
            <dt class="guide-quick-fact-label">Primary Stat</dt>
            <dd><strong>Dexterity</strong></dd>
          </div>
          <div class="guide-quick-fact">
            <dt class="guide-quick-fact-label">Difficulty</dt>
            <dd><strong>Intermediate</strong></dd>
          </div>
        </dl>

        <h3>Recommended Requirements</h3>
        <p>Praesent suscipit ligula vitae tellus malesuada, nec pulvinar nunc hendrerit. Links inside guide content, such as <a href="<?= site_url('calculators/') ?>">the calculator collection</a>, receive a consistent visible treatment.</p>
        <ul class="guide-list">
          <li>A reliable source of physical damage.</li>
          <li>Enough resistances for the selected dungeon level.</li>
          <li>Several town portals and a sensible escape route.</li>
          <li>Room in the inventory for gold, potions, and useful equipment.</li>
        </ul>

        <h3>Before Entering the Dungeon</h3>
        <ol class="guide-list guide-list-ordered">
          <li>Repair equipment and restore all spell charges.</li>
          <li>Purchase the potions required for the planned run.</li>
          <li>Check the character sheet for resistance or armor gaps.</li>
          <li>Choose a safe location for the return portal.</li>
        </ol>
      </section>

      <section class="guide-section">
        <h2>Core Mechanics</h2>
        <p>Sed tincidunt nulla sed sapien consequat, sit amet pulvinar ipsum consectetur. Nulla facilisi. Curabitur mollis lacus ac turpis placerat, vitae suscipit lectus sodales.</p>

        <aside class="guide-callout guide-callout-tip" aria-label="Strategy tip">
          <p class="guide-callout-title">UV's Tip</p>
          <p>Use the tip variant for practical advice, efficient routes, shopping targets, or tactics that come from hands-on play rather than a formal game formula.</p>
        </aside>

        <h3>Calculation Example</h3>
        <p>The mechanics block is intended for formulas, exact game steps, pseudo-code, or compact excerpts from community research.</p>
        <pre class="guide-mechanics"><code>Final Damage = Weapon Damage
             + Character Damage
             + Flat Item Damage

Apply monster-type modifier
Apply critical or special-item effects</code></pre>

        <dl class="guide-definition-list">
          <div>
            <dt>qlvl</dt>
            <dd>The quality level used by item and affix generation rules.</dd>
          </div>
          <div>
            <dt>clvl</dt>
            <dd>The current level of the player character.</dd>
          </div>
          <div>
            <dt>ilvl</dt>
            <dd>The generated item level used by relevant game systems.</dd>
          </div>
        </dl>
      </section>

      <section class="guide-section">
        <h2>Equipment Priorities</h2>
        <p>Aliquam erat volutpat. Vivamus feugiat quam sed velit congue, sed faucibus mauris pharetra. Use a responsive table for comparisons where exact values matter more than prose.</p>

        <section class="guide-table-wrap" aria-label="Equipment comparison" tabindex="0">
          <table class="guide-table">
            <caption>Example equipment targets by stage of progression</caption>
            <thead>
              <tr>
                <th scope="col">Stage</th>
                <th scope="col">Primary Goal</th>
                <th scope="col">Useful Example</th>
                <th scope="col">Priority</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th scope="row">Developing</th>
                <td>Basic survivability</td>
                <td>Resistance or life</td>
                <td><span class="guide-rating guide-rating-high">High</span></td>
              </tr>
              <tr>
                <th scope="row">Established</th>
                <td>Reliable offense</td>
                <td>Damage and chance to hit</td>
                <td><span class="guide-rating guide-rating-medium">Medium</span></td>
              </tr>
              <tr>
                <th scope="row">Endgame</th>
                <td>Optimized combination</td>
                <td>Premium or unique target</td>
                <td><span class="guide-rating guide-rating-situational">Situational</span></td>
              </tr>
            </tbody>
          </table>
        </section>

        <aside class="guide-callout guide-callout-warning" aria-label="Important warning">
          <p class="guide-callout-title">Watch for this</p>
          <p>The warning variant should be reserved for destructive actions, irreversible mistakes, multiplayer differences, or mechanics that are commonly misunderstood.</p>
        </aside>
      </section>

      <section class="guide-section">
        <h2>Advanced Considerations</h2>
        <p>Fusce in tellus id erat posuere tristique. Aenean consectetur erat sed erat posuere, nec scelerisque sem commodo. This section demonstrates longer-form editorial content and supporting quotation styles.</p>

        <blockquote class="guide-blockquote">
          <p>Not all who enter the cathedral are prepared for what waits below, but every failed descent can still teach the next character something useful.</p>
          <cite>Example community wisdom</cite>
        </blockquote>

        <h3>Special Cases</h3>
        <p>Morbi tincidunt turpis vel felis vulputate, ac luctus tortor luctus. Suspendisse potenti. Nam suscipit urna a sem tempor, a sodales arcu accumsan.</p>

        <div class="guide-card-grid">
          <article class="guide-mini-card">
            <p class="guide-mini-card-label">Single Player</p>
            <h4>Quest Differences</h4>
            <p>Use compact cards for parallel topics that deserve separation without becoming full guide sections.</p>
          </article>
          <article class="guide-mini-card">
            <p class="guide-mini-card-label">Multiplayer</p>
            <h4>Shared Dungeon Rules</h4>
            <p>Cards collapse naturally on narrow screens and do not appear in the automatic table of contents.</p>
          </article>
        </div>
      </section>

      <hr class="guide-divider">

      <section class="guide-section">
        <h2>Frequently Asked Questions</h2>

        <h3>Which headings enter the table of contents?</h3>
        <p>Every <code>h2</code> and <code>h3</code> inside <code>#guide-content</code> is included automatically. Add <code>data-in-page-nav-ignore</code> to exclude an individual heading.</p>

        <h3>Can this template contain real images?</h3>
        <p>Yes. Replace either placeholder with a semantic <code>figure</code>, an <code>img</code> using the <code>guide-image</code> class, and an optional <code>figcaption</code>.</p>

        <h3>Does every guide need custom JavaScript?</h3>
        <p>No. Every guide can load the same shared in-page navigation script. Individual guides only provide content and page metadata.</p>
      </section>
    </div>

    <footer class="guide-footer">
      <div class="guide-topic-row">
        <span class="guide-topic-label">Topics</span>
        <ul class="guide-tags">
          <li><a class="guide-tag" href="#">Hellfire</a></li>
          <li><a class="guide-tag" href="#">Strategy</a></li>
          <li><a class="guide-tag" href="#">Mechanics</a></li>
        </ul>
      </div>

      <nav class="guide-pagination" aria-label="Adjacent guides">
        <a class="guide-pagination-link guide-pagination-previous" href="#">
          <span>Previous guide</span>
          <strong>Shopping Fundamentals</strong>
        </a>
        <a class="guide-pagination-link guide-pagination-next" href="#">
          <span>Next guide</span>
          <strong>Advanced Item Hunting</strong>
        </a>
      </nav>
    </footer>
  </article>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/public_footer.php'; ?>
