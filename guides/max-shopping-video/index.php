<?php

declare(strict_types=1);

$page_title = "Max's Hellfire Shopping Video | UV's Compendium";
$page_description = "Watch Max's practical Hellfire shopping demonstration for targeting a King's Bastard Sword of Speed or Haste in DevilutionX.";
$base_path = '../../';
$current_page = 'max-shopping-video';
$page_styles = ['css/in-page-navigation.css', 'guides/css/styles.css'];
$page_scripts = ['js/in-page-navigation.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
?>

<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <li><a href="<?= site_url('guides/') ?>">Guides</a></li>
    <li aria-current="page">Max's Hellfire Shopping Video</li>
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
        data-in-page-nav-selector="h2"
        data-in-page-nav-collapse-width="1120">
        <p class="in-page-nav-placeholder">Sections appear here automatically.</p>
      </nav>
    </details>
  </aside>

  <article class="guide-entry">
    <header class="guide-header">
      <p class="eyebrow">Hellfire Video Guide</p>
      <h1 class="guide-title">Max's Hellfire Shopping Video</h1>
      <p class="guide-deck">A practical demonstration of targeted Hellfire shopping, using a King's Bastard Sword of Speed or Haste as the example.</p>

      <dl class="guide-meta">
        <div class="guide-meta-item">
          <dt>Created by</dt>
          <dd><a href="https://www.youtube.com/@Max058028tingle" target="_blank" rel="noopener noreferrer">Max058028tingle</a></dd>
        </div>
        <div class="guide-meta-item">
          <dt>Format</dt>
          <dd>Video guide</dd>
        </div>
        <div class="guide-meta-item">
          <dt>Hosted on</dt>
          <dd>YouTube</dd>
        </div>
        <div class="guide-meta-item">
          <dt>Applies to</dt>
          <dd>Hellfire / DevilutionX 1.5.4</dd>
        </div>
      </dl>
    </header>

    <div id="guide-content" class="guide-content">
      <section class="guide-section">
        <h2 id="watch-the-video">Watch the Video</h2>
        <p class="guide-lead">Max demonstrates how to narrow Hellfire's shop inventory while targeting any King's Bastard Sword of Speed or Haste.</p>

        <figure class="guide-figure guide-featured-figure">
          <div class="guide-video-embed">
            <iframe
              src="https://www.youtube-nocookie.com/embed/c8MaZZezeMQ"
              title="How to shop in Hellfire: any King's Bastard Sword of Speed or Haste"
              loading="lazy"
              referrerpolicy="strict-origin-when-cross-origin"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              allowfullscreen></iframe>
          </div>
          <figcaption class="guide-figcaption">
            “How to shop in Hellfire (Goal ANY King's bastard sword of speed/haste) Multiplayer DevilutionX 1.5.4” by Max058028tingle.
          </figcaption>
        </figure>

        <p>If the embedded player is unavailable, <a href="https://www.youtube.com/watch?v=c8MaZZezeMQ" target="_blank" rel="noopener noreferrer">watch the video directly on YouTube</a>.</p>
      </section>

      <section class="guide-section">
        <h2 id="related-shopping-resources">Related Shopping Resources</h2>
        <p>For a written explanation of vendor rules, shopper levels, stat filtering, value anchors, and targeted equipment shopping, read <a href="<?= site_url('guides/shopping/') ?>">UV's Shopping &amp; Affixes</a>.</p>
        <p>The site's calculators can help you research and plan a specific target:</p>
        <ul class="guide-list">
          <li><a class="guide-tool-link" href="<?= site_url('calculators/premium-item-checker/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Premium Item Checker (opens in a new tab)">Hellfire Premium Item Checker</a> checks whether an item and affix combination is valid.</li>
          <li><a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)">Hellfire Shop Qlvl Calculator</a> compares shopper levels and available vendor slots.</li>
          <li><a class="guide-tool-link" href="<?= site_url('calculators/item-price/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Item Price Calculator (opens in a new tab)">Hellfire Item Price Calculator</a> estimates value and checks the normal shop-price limit.</li>
        </ul>
      </section>
    </div>

    <footer class="guide-footer">
      <div class="guide-topic-row">
        <span class="guide-topic-label">Topics</span>
        <ul class="guide-tags">
          <li><a class="guide-tag" href="<?= site_url('guides/') ?>">Hellfire</a></li>
          <li><a class="guide-tag" href="<?= site_url('guides/') ?>">Shopping</a></li>
          <li><a class="guide-tag" href="<?= site_url('guides/') ?>">Video Guide</a></li>
        </ul>
      </div>
    </footer>
  </article>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/public_footer.php'; ?>
