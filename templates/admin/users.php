<?php
/**
 * @var \Uvs\Http\View $view
 * @var list<array<string, mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $query
 * @var string $status
 * @var string $role
 * @var array<string, int> $counts
 */
$view->partial('admin-header', ['section' => 'users', 'title' => 'Users', 'trail' => [['Users', null]]]);
$statusTones = ['pending' => 'info', 'active' => 'success', 'suspended' => 'danger', 'rejected' => 'muted'];
?>
<section class="section-panel flow" aria-labelledby="filter-title">
  <h2 id="filter-title" class="sr-only">Filter users</h2>
  <form class="filter-form" method="get" action="<?= $view->url('admin/users/') ?>">
    <div class="form-field"><label for="q">Username or email</label><input id="q" name="q" type="search" maxlength="100" value="<?= h($query) ?>"></div>
    <div class="form-field"><label for="status">Status</label>
      <select id="status" name="status">
        <option value="">Any status</option>
        <?php foreach ($counts as $value => $count): ?>
          <option value="<?= h($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= h(ucfirst($value)) ?> (<?= $count ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label for="role">Role</label>
      <select id="role" name="role">
        <option value="">Any role</option>
        <option value="member"<?= $role === 'member' ? ' selected' : '' ?>>Member</option>
        <option value="admin"<?= $role === 'admin' ? ' selected' : '' ?>>Administrator</option>
      </select>
    </div>
    <div class="form-field form-field-end"><button class="button button-secondary" type="submit">Filter</button></div>
  </form>
</section>

<section class="section-panel flow" aria-labelledby="results-title">
  <h2 id="results-title"><?= number_format($total) ?> account<?= $total === 1 ? '' : 's' ?></h2>
  <?php if ($rows === []): ?>
    <p class="text-muted">No accounts match these filters.</p>
  <?php else: ?>
    <div class="table-wrap" tabindex="0" role="region" aria-labelledby="results-title">
      <table class="data-table">
        <thead><tr><th scope="col">Account</th><th scope="col">Status</th><th scope="col">Role</th><th scope="col">Guides</th><th scope="col">Joined</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <th scope="row" data-label="Account">
                <a href="<?= $view->url('admin/users/' . (int) $row['id'] . '/') ?>"><?= h((string) $row['username']) ?></a>
                <span class="cell-detail"><?= h((string) $row['email']) ?></span>
              </th>
              <td data-label="Status"><?php $view->partial('status-badge', ['label' => ucfirst((string) $row['status']), 'tone' => $statusTones[$row['status']] ?? 'muted']); ?></td>
              <td data-label="Role"><?= $row['role'] === 'admin' ? 'Administrator' : ucfirst((string) $row['role']) ?></td>
              <td data-label="Guides"><?= (int) $row['published_count'] ?> published / <?= (int) $row['submitted_count'] ?> submitted</td>
              <td data-label="Joined"><?= $view->time((string) $row['created_at'], 'M j, Y') ?></td>
              <td data-label="Actions">
                <?php if ($row['status'] === 'pending'): ?>
                  <form method="post" action="<?= $view->url('admin/users/' . (int) $row['id'] . '/status/') ?>">
                    <?= $view->csrf() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="return" value="list">
                    <button class="button button-primary button-small" type="submit">Approve<span class="sr-only"> <?= h((string) $row['username']) ?></span></button>
                  </form>
                <?php else: ?>
                  <a class="button button-secondary button-small" href="<?= $view->url('admin/users/' . (int) $row['id'] . '/') ?>">Manage<span class="sr-only"> <?= h((string) $row['username']) ?></span></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => 'admin/users/', 'query' => ['q' => $query, 'status' => $status, 'role' => $role]]); ?>
  <?php endif; ?>
</section>
