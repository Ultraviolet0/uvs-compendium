<?php
/**
 * @var \Uvs\Http\View $view
 * @var list<array<string, mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $action
 * @var string $type
 * @var array<string, string> $labels
 */
use Uvs\Support\Text;

$view->partial('admin-header', ['section' => 'audit', 'title' => 'Audit log', 'trail' => [['Audit log', null]], 'intro' => 'Privileged actions, newest first. Passwords, tokens, and secrets are never recorded.']);
?>
<section class="section-panel flow" aria-labelledby="filter-title">
  <h2 id="filter-title" class="sr-only">Filter</h2>
  <form class="filter-form" method="get" action="<?= $view->url('admin/audit/') ?>">
    <div class="form-field"><label for="action">Action</label>
      <select id="action" name="action"><option value="">All actions</option>
        <?php foreach ($labels as $key => $label): ?><option value="<?= h($key) ?>"<?= $action === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-field"><label for="type">Target</label>
      <select id="type" name="type"><option value="">All targets</option>
        <?php foreach (['user' => 'Users', 'guide' => 'Guides', 'setting' => 'Settings', 'media' => 'Media', 'system' => 'System'] as $key => $label): ?><option value="<?= h($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-field form-field-end"><button class="button button-secondary" type="submit">Filter</button></div>
  </form>
</section>

<section class="section-panel flow" aria-labelledby="events-title">
  <h2 id="events-title"><?= number_format($total) ?> event<?= $total === 1 ? '' : 's' ?></h2>
  <?php if ($rows === []): ?>
    <p class="text-muted">No events recorded.</p>
  <?php else: ?>
    <div class="table-wrap" tabindex="0" role="region" aria-labelledby="events-title">
      <table class="data-table">
        <thead><tr><th scope="col">When (UTC)</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Details</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): $meta = $row['metadata'] !== null ? json_decode((string) $row['metadata'], true) : []; ?>
            <tr>
              <td data-label="When"><?= h(Text::date((string) $row['created_at'], 'Y-m-d H:i')) ?></td>
              <td data-label="Actor"><?= h((string) $row['actor_label']) ?></td>
              <th scope="row" data-label="Action"><?= h($labels[$row['action']] ?? (string) $row['action']) ?></th>
              <td data-label="Target">
                <?php if ($row['target_type'] === 'user' && $row['target_id'] !== null): ?>
                  <a href="<?= $view->url('admin/users/' . (int) $row['target_id'] . '/') ?>"><?= h((string) $row['target_label']) ?></a>
                <?php elseif ($row['target_type'] === 'guide' && $row['target_id'] !== null && $row['action'] !== 'guide.purged'): ?>
                  <a href="<?= $view->url('admin/guides/' . (int) $row['target_id'] . '/') ?>"><?= h((string) $row['target_label']) ?></a>
                <?php else: ?>
                  <?= h((string) ($row['target_label'] ?? '')) ?>
                <?php endif; ?>
              </td>
              <td data-label="Details"><?= is_array($meta) && $meta !== [] ? h(implode('; ', array_map(static fn ($k, $v) => $k . ': ' . (is_scalar($v) ? (string) $v : json_encode($v)), array_keys($meta), $meta))) : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => 'admin/audit/', 'query' => ['action' => $action, 'type' => $type]]); ?>
  <?php endif; ?>
</section>
