<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $guide
 * @var array<string, mixed> $revision
 * @var ?array<string, mixed> $previous
 * @var string $against
 * @var ?array{title: ?array{0: string, 1: string}, summary: ?array{0: string, 1: string}, body: list<array{0: string, 1: string}>} $diff
 */
use Uvs\Support\Text;

$number = (int) $revision['revision_number'];
$view->partial('admin-header', ['section' => 'guides', 'title' => 'Revision ' . $number, 'trail' => [['Guides', 'admin/guides/'], [(string) $guide['title'], 'admin/guides/' . (int) $guide['id'] . '/'], ['Revision ' . $number, null]]]);
$base = $view->url('admin/guides/' . (int) $guide['id'] . '/revisions/' . (int) $revision['id'] . '/');
?>
<section class="section-panel flow" aria-labelledby="meta-title">
  <h2 id="meta-title" class="sr-only">Revision details</h2>
  <dl class="detail-list detail-list-inline">
    <div><dt>Type</dt><dd><?= h(ucwords(str_replace('_', ' ', (string) $revision['kind']))) ?></dd></div>
    <div><dt>Recorded</dt><dd><?= h(Text::dateTime((string) $revision['created_at'])) ?></dd></div>
    <div><dt>By</dt><dd><?= h((string) ($revision['created_by_username'] ?? 'unknown')) ?></dd></div>
    <?php if ($revision['note']): ?><div><dt>Note</dt><dd><?= h((string) $revision['note']) ?></dd></div><?php endif; ?>
  </dl>
  <p class="field-help">Revisions are read-only records. Authors cannot change them.</p>
  <nav class="filter-tabs" aria-label="Comparison">
    <ul>
      <li><a class="filter-tab<?= $against === 'previous' ? ' is-active' : '' ?>" href="<?= $base ?>"<?= $against === 'previous' ? ' aria-current="page"' : '' ?>>Compare with revision <?= $previous !== null ? (int) $previous['revision_number'] : '—' ?></a></li>
      <li><a class="filter-tab<?= $against === 'current' ? ' is-active' : '' ?>" href="<?= $base ?>?against=current"<?= $against === 'current' ? ' aria-current="page"' : '' ?>>Compare with current working copy</a></li>
    </ul>
  </nav>
</section>

<section class="section-panel flow" aria-labelledby="diff-title">
  <h2 id="diff-title"><?= $against === 'current' ? 'From this revision to the working copy' : 'Changes in this revision' ?></h2>
  <?php if ($diff === null): ?>
    <p class="text-muted">This is the first revision, so there is nothing earlier to compare with.</p>
  <?php else: ?>
    <?php if ($diff['title'] !== null): ?><p><strong>Title:</strong> <del><?= h($diff['title'][0]) ?></del> → <ins><?= h($diff['title'][1]) ?></ins></p><?php endif; ?>
    <?php if ($diff['summary'] !== null): ?><p><strong>Summary:</strong> <del><?= h($diff['summary'][0]) ?></del> → <ins><?= h($diff['summary'][1]) ?></ins></p><?php endif; ?>
    <?php $view->partial('diff', ['operations' => $diff['body']]); ?>
  <?php endif; ?>
</section>

<details class="section-panel">
  <summary>Full Markdown of revision <?= $number ?></summary>
  <p><strong><?= h((string) $revision['title']) ?></strong></p>
  <p><?= h((string) $revision['summary']) ?></p>
  <pre class="revision-source"><code><?= h((string) $revision['body']) ?></code></pre>
</details>
