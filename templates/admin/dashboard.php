<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var ?array<string, int> $userCounts
 * @var array<string, int> $guideCounts
 * @var list<array<string, mixed>> $queue
 * @var list<array<string, mixed>> $pendingUsers
 * @var list<array<string, mixed>> $audit
 * @var array<string, bool|int> $settings
 * @var string $turnstile
 * @var string $mail
 */
use Uvs\Admin\AuditLog;

$view->partial('admin-header', ['section' => 'dashboard', 'title' => 'Administration', 'trail' => [], 'intro' => 'Review new members and guide submissions, and keep an eye on the site.']);
?>
<?php if ($user['mfa_enabled_at'] === null): ?>
  <p class="notice notice-warning">Your administrator account does not use two-factor authentication. <a class="text-link" href="<?= $view->url('account/security/two-factor/') ?>">Turn it on now</a>.</p>
<?php endif; ?>
<?php if ($turnstile === 'misconfigured' || $mail === 'disabled'): ?>
  <div class="notice notice-info">
    <?php if ($turnstile === 'misconfigured'): ?><p>Cloudflare Turnstile is not configured, so new signups are unavailable.</p><?php endif; ?>
    <?php if ($mail === 'disabled'): ?><p>Outgoing email is not configured, so password reset by email is unavailable.</p><?php endif; ?>
  </div>
<?php endif; ?>

<dl class="stat-grid stat-grid-admin">
  <?php if ($userCounts !== null): ?>
    <div class="stat-card<?= $userCounts['pending'] > 0 ? ' stat-attention' : '' ?>"><dt class="stat-label"><a href="<?= $view->url('admin/users/?status=pending') ?>">Pending accounts</a></dt><dd class="stat-value"><?= $userCounts['pending'] ?></dd></div>
  <?php endif; ?>
  <div class="stat-card<?= $guideCounts['queue'] > 0 ? ' stat-attention' : '' ?>"><dt class="stat-label"><a href="<?= $view->url('admin/guides/?filter=queue') ?>">Guides in review</a></dt><dd class="stat-value"><?= $guideCounts['queue'] ?></dd></div>
  <div class="stat-card"><dt class="stat-label"><a href="<?= $view->url('admin/guides/?filter=needs_changes') ?>">Needs changes</a></dt><dd class="stat-value"><?= $guideCounts['needs_changes'] ?></dd></div>
  <div class="stat-card"><dt class="stat-label"><a href="<?= $view->url('admin/guides/?filter=published') ?>">Published guides</a></dt><dd class="stat-value"><?= $guideCounts['published'] ?></dd></div>
  <?php if ($userCounts !== null): ?>
    <div class="stat-card"><dt class="stat-label"><a href="<?= $view->url('admin/users/?status=active') ?>">Active members</a></dt><dd class="stat-value"><?= $userCounts['active'] ?></dd></div>
    <div class="stat-card"><dt class="stat-label"><a href="<?= $view->url('admin/users/?status=suspended') ?>">Suspended</a></dt><dd class="stat-value"><?= $userCounts['suspended'] ?></dd></div>
  <?php endif; ?>
</dl>

<div class="two-column">
  <section class="section-panel flow" aria-labelledby="queue-title">
    <h2 id="queue-title">Review queue</h2>
    <?php if ($queue === []): ?>
      <p class="text-muted">No guides are waiting for review.</p>
    <?php else: ?>
      <ul class="admin-list">
        <?php foreach ($queue as $guide): ?>
          <li>
            <a href="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/') ?>"><?= h((string) $guide['title']) ?></a>
            <span class="text-muted">by <?= h((string) ($guide['author_username'] ?? 'a former member')) ?> · submitted <?= $view->time((string) $guide['submitted_at'], 'M j') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if ($userCounts !== null): ?>
    <section class="section-panel flow" aria-labelledby="pending-title">
      <h2 id="pending-title">Accounts awaiting approval</h2>
      <p class="field-help">Automatic approval is <strong><?= $settings['auto_approve_accounts'] ? 'on' : 'off' ?></strong>. <a class="text-link" href="<?= $view->url('admin/settings/') ?>">Change</a></p>
      <?php if ($pendingUsers === []): ?>
        <p class="text-muted">Nobody is waiting.</p>
      <?php else: ?>
        <ul class="admin-list">
          <?php foreach ($pendingUsers as $pending): ?>
            <li class="admin-list-row">
              <span><a href="<?= $view->url('admin/users/' . (int) $pending['id'] . '/') ?>"><?= h((string) $pending['username']) ?></a>
                <span class="text-muted">joined <?= $view->time((string) $pending['created_at'], 'M j') ?></span></span>
              <form method="post" action="<?= $view->url('admin/users/' . (int) $pending['id'] . '/status/') ?>">
                <?= $view->csrf() ?>
                <input type="hidden" name="action" value="approve"><input type="hidden" name="return" value="list">
                <button class="button button-primary button-small" type="submit">Approve<span class="sr-only"> <?= h((string) $pending['username']) ?></span></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<?php if ($audit !== []): ?>
  <section class="section-panel flow" aria-labelledby="recent-title">
    <div class="section-heading-row">
      <h2 id="recent-title">Recent activity</h2>
      <a class="text-link" href="<?= $view->url('admin/audit/') ?>">Full audit log</a>
    </div>
    <ul class="admin-list">
      <?php foreach ($audit as $event): ?>
        <li><strong><?= h(AuditLog::LABELS[$event['action']] ?? (string) $event['action']) ?></strong>
          <?= $event['target_label'] !== null ? '· ' . h((string) $event['target_label']) : '' ?>
          <span class="text-muted">by <?= h((string) $event['actor_label']) ?>, <?= h(\Uvs\Support\Text::dateTime((string) $event['created_at'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
