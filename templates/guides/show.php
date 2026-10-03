<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $guide published snapshot (public) or working copy (preview)
 * @var string $html sanitized output of MarkdownRenderer
 * @var bool $preview
 * @var ?array{label: string, tone: string, detail: string} $status
 */
use Uvs\Guides\GuideWorkflow;

$author = $guide['author_username'] ?? null;
$authorActive = ($guide['author_status'] ?? null) === 'active';
$published = $guide['first_published_at'] ?? null;
$updated = $guide['published_at'] ?? null;
?>
<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= $view->url() ?>">Home</a></li>
    <li><a href="<?= $view->url('guides/') ?>">Guides</a></li>
    <li aria-current="page"><?= h((string) $guide['title']) ?></li>
  </ol>
</nav>

<?php if ($preview): ?>
  <div class="notice notice-warning preview-banner">
    <p><strong>Private preview.</strong> This is the working copy (<?= h($status['label'] ?? '') ?>). Readers only ever see the version an administrator published.</p>
  </div>
<?php endif; ?>

<div class="guide-layout">
  <aside class="in-page-nav-column" aria-label="Page sections">
    <details class="in-page-nav-panel" open>
      <summary class="in-page-nav-summary">On this page</summary>
      <nav class="in-page-nav" aria-label="Table of contents" data-in-page-nav data-in-page-nav-target="guide-content"
        data-in-page-nav-selector="h2, h3" data-in-page-nav-collapse-width="1120">
        <p class="in-page-nav-placeholder">Sections appear here automatically.</p>
      </nav>
    </details>
  </aside>

  <article class="guide-entry community-guide">
    <header class="guide-header">
      <p class="eyebrow">Community Guide</p>
      <h1 class="guide-title"><?= h((string) $guide['title']) ?></h1>
      <?php if ((string) $guide['summary'] !== ''): ?>
        <p class="guide-deck"><?= h((string) $guide['summary']) ?></p>
      <?php endif; ?>
      <dl class="guide-meta">
        <div class="guide-meta-item">
          <dt>Written by</dt>
          <dd class="guide-byline">
            <?php if ($author !== null): ?>
              <?php uvs_avatar(['username' => $author, 'avatar_public_id' => $guide['author_avatar_public_id'] ?? null, 'avatar_extension' => $guide['author_avatar_extension'] ?? null], 'xs'); ?>
              <?php if ($authorActive): ?>
                <a href="<?= $view->url('members/' . rawurlencode((string) $author) . '/') ?>"><?= h((string) $author) ?></a>
              <?php else: ?>
                <?= h((string) $author) ?>
              <?php endif; ?>
            <?php else: ?>
              A former member
            <?php endif; ?>
          </dd>
        </div>
        <?php if ($published !== null): ?>
          <div class="guide-meta-item"><dt>Published</dt><dd><?= $view->time((string) $published) ?></dd></div>
        <?php endif; ?>
        <?php if ($updated !== null && $updated !== $published): ?>
          <div class="guide-meta-item"><dt>Updated</dt><dd><?= $view->time((string) $updated) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($guide['applies_to'])): ?>
          <div class="guide-meta-item"><dt>Applies to</dt><dd><?= h(GuideWorkflow::APPLIES_TO[$guide['applies_to']] ?? '') ?></dd></div>
        <?php endif; ?>
      </dl>
    </header>

    <div id="guide-content" class="guide-content community-guide-content">
      <?= $html ?>
    </div>

    <footer class="guide-footer">
      <p class="community-guide-note">Community guides are written by members of UV's Compendium and reviewed by an administrator before publication. Mechanics claims follow the authors’ own testing; check the <a href="<?= $view->url('calculators/') ?>">calculators</a> and <a href="<?= $view->url('reference/jarulf162.pdf') ?>">Jarulf's Guide</a> when precision matters.</p>
    </footer>
  </article>
</div>
