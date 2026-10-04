<?php
/**
 * @var \Uvs\Http\View $view
 * @var list<array{0: string, 1: ?string}> $trail label => path (null for the current page)
 */
?>
<nav class="app-breadcrumbs" aria-label="Breadcrumb">
  <ol class="app-breadcrumb-list">
    <li><a href="<?= $view->url() ?>">Home</a></li>
    <?php foreach ($trail as [$label, $path]): ?>
      <?php if ($path === null): ?>
        <li aria-current="page"><?= h($label) ?></li>
      <?php else: ?>
        <li><a href="<?= $view->url($path) ?>"><?= h($label) ?></a></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
</nav>
