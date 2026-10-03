<?php
/**
 * @var \Uvs\Http\View $view
 * @var string $section
 */
$items = [
  'dashboard' => ['Overview', 'admin/', 'admin.access'],
  'users' => ['Users', 'admin/users/', 'users.manage'],
  'guides' => ['Guides', 'admin/guides/', 'guides.moderate'],
  'settings' => ['Settings', 'admin/settings/', 'settings.manage'],
  'audit' => ['Audit log', 'admin/audit/', 'audit.view'],
];
?>
<nav class="tab-nav admin-nav" aria-label="Administration sections">
  <ul class="tab-nav-list">
    <?php foreach ($items as $key => [$label, $path, $ability]): ?>
      <?php if (!$view->can($ability)) continue; ?>
      <li><a class="tab-nav-link<?= $key === $section ? ' is-active' : '' ?>" href="<?= $view->url($path) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>><?= h($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>
