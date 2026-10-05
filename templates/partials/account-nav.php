<?php
/**
 * @var \Uvs\Http\View $view
 * @var string $active
 */
$accountUser = $view->user();
$items = [
  'dashboard' => ['Overview', 'account/'],
  'profile' => ['Profile', 'account/profile/'],
  'characters' => ['Characters', 'account/characters/'],
  'guides' => ['Guides', 'account/guides/'],
  'security' => ['Security', 'account/security/'],
];
?>
<nav class="tab-nav" aria-label="Account sections">
  <ul class="tab-nav-list">
    <?php foreach ($items as $key => [$label, $path]): ?>
      <li><a class="tab-nav-link<?= $key === $active ? ' is-active' : '' ?>" href="<?= $view->url($path) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>
