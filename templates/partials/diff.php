<?php
/**
 * @var list<array{0: string, 1: string}> $operations
 */
$added = count(array_filter($operations, static fn ($op) => $op[0] === '+'));
$removed = count(array_filter($operations, static fn ($op) => $op[0] === '-'));
?>
<p class="field-help"><span class="diff-added-count"><?= $added ?> line<?= $added === 1 ? '' : 's' ?> added</span>, <span class="diff-removed-count"><?= $removed ?> removed</span>.</p>
<?php if ($added + $removed === 0): ?>
  <p class="text-muted">The text is identical.</p>
<?php else: ?>
  <div class="diff" role="region" aria-label="Line differences" tabindex="0">
    <?php foreach ($operations as [$op, $line]): ?>
      <?php if ($op === '…'): ?>
        <div class="diff-line diff-gap"><span class="diff-marker" aria-hidden="true">⋯</span><span class="sr-only">Unchanged lines omitted</span></div>
      <?php else: ?>
        <div class="diff-line<?= $op === '+' ? ' diff-add' : ($op === '-' ? ' diff-remove' : '') ?>"><span class="diff-marker"><?= $op === '+' ? '+<span class="sr-only"> added</span>' : ($op === '-' ? '−<span class="sr-only"> removed</span>' : ' ') ?></span><span class="diff-text"><?= $line === '' ? '&nbsp;' : h($line) ?></span></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
