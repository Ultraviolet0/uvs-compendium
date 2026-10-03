<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $member
 * @var list<array<string, mixed>> $guides
 * @var int $mediaBytes
 * @var int $characters
 * @var list<array<string, mixed>> $history
 * @var list<string> $actions
 * @var bool $isSelf
 */
use Uvs\Guides\GuideStatus;
use Uvs\Support\Text;

$view->partial('admin-header', ['section' => 'users', 'title' => (string) $member['username'], 'trail' => [['Users', 'admin/users/'], [(string) $member['username'], null]]]);
$labels = [
  'approve' => ['Approve', 'Activate this pending (or previously rejected) account.', 'button-primary'],
  'reject' => ['Reject', 'Decline this account application. They will not be able to sign in.', 'button-danger'],
  'suspend' => ['Suspend', 'Sign the member out everywhere and block sign-in. Reversible.', 'button-danger'],
  'reactivate' => ['Reactivate', 'Restore access to this account.', 'button-primary'],
];
?>
<?php $view->partial('error-summary', ['fields' => []]); ?>

<div class="two-column">
  <section class="section-panel flow" aria-labelledby="account-title">
    <div class="identity-row">
      <?php uvs_avatar($member, 'lg'); ?>
      <div class="flow">
        <h2 id="account-title">Account</h2>
        <p class="identity-meta">
          <?php $view->partial('status-badge', ['label' => ucfirst((string) $member['status']), 'tone' => ['pending' => 'info', 'active' => 'success', 'suspended' => 'danger', 'rejected' => 'muted'][$member['status']] ?? 'muted']); ?>
          <span><?= $member['role'] === 'admin' ? 'Administrator' : ucfirst((string) $member['role']) ?></span>
        </p>
      </div>
    </div>
    <dl class="detail-list">
      <div><dt>Email (private)</dt><dd><?= h((string) $member['email']) ?></dd></div>
      <div><dt>Joined</dt><dd><?= h(Text::dateTime((string) $member['created_at'])) ?></dd></div>
      <div><dt>Last sign-in</dt><dd><?= $member['last_login_at'] ? h(Text::dateTime((string) $member['last_login_at'])) : 'Never' ?></dd></div>
      <div><dt>Two-factor</dt><dd><?= $member['mfa_enabled_at'] ? 'On' : 'Off' ?></dd></div>
      <div><dt>Characters</dt><dd><?= $characters ?></dd></div>
      <div><dt>Image storage</dt><dd><?= h(Text::bytes($mediaBytes)) ?></dd></div>
      <?php if ($member['status_reason']): ?><div><dt>Message shown to member</dt><dd><?= h((string) $member['status_reason']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($member['status'] === 'active'): ?>
      <p><a class="text-link" href="<?= $view->url('members/' . rawurlencode((string) $member['username']) . '/') ?>">Public profile</a></p>
    <?php endif; ?>
  </section>

  <section class="section-panel flow" aria-labelledby="actions-title">
    <h2 id="actions-title">Account status</h2>
    <?php if ($isSelf): ?>
      <p class="text-muted">You cannot change the status or role of your own account.</p>
    <?php elseif ($actions === []): ?>
      <p class="text-muted">No status changes are available.</p>
    <?php else: ?>
      <?php foreach ($actions as $action): [$label, $help, $class] = $labels[$action]; $needsConfirm = in_array($action, ['reject', 'suspend'], true); ?>
        <form class="form-stack action-form" method="post" action="<?= $view->url('admin/users/' . (int) $member['id'] . '/status/') ?>">
          <?= $view->csrf() ?>
          <input type="hidden" name="action" value="<?= h($action) ?>">
          <p class="field-help"><?= h($help) ?></p>
          <?php if ($action !== 'approve'): ?>
            <div class="form-field">
              <label for="reason-<?= h($action) ?>">Message to the member <span class="optional">(optional, shown to them)</span></label>
              <input id="reason-<?= h($action) ?>" name="reason" type="text" maxlength="500">
            </div>
          <?php endif; ?>
          <?php if ($needsConfirm): ?>
            <div class="form-check">
              <input id="confirm-<?= h($action) ?>" name="confirm" type="checkbox" value="1" required>
              <label for="confirm-<?= h($action) ?>"><?= h($label) ?> <?= h((string) $member['username']) ?></label>
            </div>
          <?php endif; ?>
          <button class="button <?= h($class) ?>" type="submit"><?= h($label) ?> account</button>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$isSelf && $member['status'] === 'active'): ?>
      <form class="form-stack action-form" method="post" action="<?= $view->url('admin/users/' . (int) $member['id'] . '/role/') ?>">
        <?= $view->csrf() ?>
        <h3>Role</h3>
        <div class="form-field">
          <label for="role">Role</label>
          <select id="role" name="role">
            <option value="member"<?= $member['role'] === 'member' ? ' selected' : '' ?>>Member</option>
            <option value="admin"<?= $member['role'] === 'admin' ? ' selected' : '' ?>>Administrator</option>
          </select>
        </div>
        <div class="form-check">
          <input id="confirm-role" name="confirm" type="checkbox" value="1" required>
          <label for="confirm-role">Change the role of <?= h((string) $member['username']) ?></label>
        </div>
        <button class="button button-secondary" type="submit">Update role</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<section class="section-panel flow" aria-labelledby="note-title">
  <h2 id="note-title">Administrator note</h2>
  <p class="field-help">Private to administrators. Never shown to the member or the public.</p>
  <form class="form-stack" method="post" action="<?= $view->url('admin/users/' . (int) $member['id'] . '/note/') ?>">
    <?= $view->csrf() ?>
    <div class="form-field">
      <label for="admin_note" class="sr-only">Administrator note</label>
      <textarea id="admin_note" name="admin_note" rows="4" maxlength="5000"><?= h((string) ($member['admin_note'] ?? '')) ?></textarea>
    </div>
    <button class="button button-secondary" type="submit">Save note</button>
  </form>
</section>

<section class="section-panel flow" aria-labelledby="guides-title">
  <h2 id="guides-title">Guides</h2>
  <?php if ($guides === []): ?>
    <p class="text-muted">No guides.</p>
  <?php else: ?>
    <ul class="admin-list">
      <?php foreach ($guides as $guide): $status = GuideStatus::describe($guide); ?>
        <li class="admin-list-row"><a href="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/') ?>"><?= h((string) $guide['title']) ?></a>
          <?php $view->partial('status-badge', ['label' => $status['label'], 'tone' => $status['tone']]); ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="section-panel flow" aria-labelledby="history-title">
  <h2 id="history-title">History</h2>
  <?php $view->partial('audit-list', ['events' => $history]); ?>
</section>
