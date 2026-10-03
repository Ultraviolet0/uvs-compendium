<?php
/** @var list<array{type: string, message: string}> $page_flashes */
$flashes = $GLOBALS['page_flashes'] ?? [];
?>
<?php if ($flashes !== []): ?>
  <div class="flash-stack" role="status">
    <?php foreach ($flashes as $flash): ?>
      <p class="notice notice-<?= h(in_array($flash['type'], ['success', 'info', 'warning', 'error'], true) ? $flash['type'] : 'info') ?>"><?= h((string) $flash['message']) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
