<?php
/**
 * @var \Uvs\Http\View $view
 * @var int $page
 * @var int $pages
 * @var string $path base path without query string
 * @var array<string, string> $query extra query parameters
 */
if ($pages <= 1) {
  return;
}
$link = static fn (int $number): string => $view->url($path) . '?' . h(http_build_query(array_filter($query + ['page' => $number], static fn ($value) => $value !== '' && $value !== null)));
?>
<nav class="pagination" aria-label="Pages">
  <?php if ($page > 1): ?>
    <a class="button button-secondary button-small" href="<?= $link($page - 1) ?>" rel="prev">Previous</a>
  <?php endif; ?>
  <span class="pagination-status">Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?>
    <a class="button button-secondary button-small" href="<?= $link($page + 1) ?>" rel="next">Next</a>
  <?php endif; ?>
</nav>
