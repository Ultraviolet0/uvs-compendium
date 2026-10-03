<?php
/**
 * @var \Uvs\Http\View $view
 * @var string $section
 * @var string $title
 * @var list<array{0: string, 1: ?string}> $trail
 * @var ?string $intro
 */
$view->partial('breadcrumbs', ['trail' => array_merge([['Administration', $section === 'dashboard' && count($trail) === 0 ? null : 'admin/']], $trail)]);
?>
<section class="section-panel flow admin-header" aria-labelledby="page-title">
  <p class="eyebrow">Administration</p>
  <h1 id="page-title"><?= h($title) ?></h1>
  <?php if (!empty($intro)): ?><p><?= h($intro) ?></p><?php endif; ?>
  <?php $view->partial('admin-nav', ['section' => $section]); ?>
</section>
