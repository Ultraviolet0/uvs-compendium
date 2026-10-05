<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var array<string, list<array<string, mixed>>> $groups
 * @var int $guideCount
 * @var int $characterCount
 * @var bool $mfaEnabled
 * @var bool $submissionsOpen
 */
use Uvs\Guides\GuideStatus;

$sections = [
  'attention' => ['Needs your attention', 'Guides an administrator sent back with notes.'],
  'drafts' => ['Drafts', 'Private until you submit them.'],
  'review' => ['In review', 'Waiting for an administrator.'],
  'published' => ['Published', 'Live guides, including any update you are preparing.'],
  'closed' => ['Rejected', 'Submissions that were not accepted.'],
];
$view->partial('breadcrumbs', ['trail' => [['Your account', null]]]);
?>
<section class="section-panel flow-lg dashboard-hero" aria-labelledby="page-title">
  <div class="identity-row">
    <?php uvs_avatar($user, 'lg'); ?>
    <div class="flow">
      <p class="eyebrow">Member dashboard</p>
      <h1 id="page-title"><?= h((string) $user['username']) ?></h1>
      <p class="identity-meta">
        <?php $view->partial('status-badge', ['label' => uvs_account_status_label($user), 'tone' => $user['status'] === 'active' ? 'success' : 'info']); ?>
        <span>Joined <?= $view->time((string) $user['created_at'], 'F Y') ?></span>
      </p>
    </div>
  </div>
  <?php $view->partial('account-nav', ['active' => 'dashboard']); ?>
</section>

<?php $view->partial('account-status', ['user' => $user]); ?>

<dl class="stat-grid">
  <div class="stat-card"><dt class="stat-label">Guides</dt><dd class="stat-value"><?= $guideCount ?></dd></div>
  <div class="stat-card"><dt class="stat-label">Published</dt><dd class="stat-value"><?= count($groups['published']) ?></dd></div>
  <div class="stat-card"><dt class="stat-label">Characters</dt><dd class="stat-value"><?= $characterCount ?></dd></div>
  <div class="stat-card"><dt class="stat-label">Two-factor</dt><dd class="stat-value"><?= $mfaEnabled ? 'On' : 'Off' ?></dd></div>
</dl>

<section class="section-panel flow" aria-labelledby="guides-title">
  <div class="section-heading-row">
    <h2 id="guides-title">Your guides</h2>
    <?php if ($view->can('guide.create')): ?>
      <a class="button button-primary" href="<?= $view->url('account/guides/new/') ?>">Write a guide</a>
    <?php endif; ?>
  </div>
  <?php if (!$submissionsOpen && $user['status'] === 'active'): ?>
    <p class="notice notice-info">Guide submissions are paused right now. You can keep writing drafts.</p>
  <?php endif; ?>

  <?php if ($guideCount === 0): ?>
    <div class="empty-state">
      <p class="empty-state-title">No guides yet</p>
      <?php if ($view->can('guide.create')): ?>
        <p>Share a route, a shopping trick, or a class build. Drafts stay private until you submit them.</p>
      <?php else: ?>
        <p>Once your account is approved you can write guides here.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($sections as $key => [$title, $help]): ?>
      <?php if ($groups[$key] === []) continue; ?>
      <div class="guide-group flow">
        <h3><?= h($title) ?> <span class="count-pill"><?= count($groups[$key]) ?></span></h3>
        <p class="text-muted"><?= h($help) ?></p>
        <ul class="guide-rows">
          <?php foreach ($groups[$key] as $guide): $status = GuideStatus::describe($guide); ?>
            <li class="guide-row">
              <div class="guide-row-main">
                <a class="guide-row-title" href="<?= $view->url('account/guides/' . (int) $guide['id'] . '/edit/') ?>"><?= h((string) $guide['title']) ?></a>
                <p class="guide-row-meta">
                  <?php $view->partial('status-badge', ['label' => $status['label'], 'tone' => $status['tone']]); ?>
                  <span>Updated <?= $view->time((string) $guide['updated_at'], 'M j, Y') ?></span>
                </p>
                <?php if ($guide['review_status'] === 'needs_changes' && $guide['moderation_note']): ?>
                  <p class="moderation-note"><strong>Administrator note:</strong> <?= nl2br(h((string) $guide['moderation_note'])) ?></p>
                <?php endif; ?>
              </div>
              <?php if ($guide['visibility'] === 'published'): ?>
                <a class="button button-secondary button-small" href="<?= $view->url('guides/' . $guide['slug'] . '/') ?>">View live</a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php if (!$mfaEnabled && in_array($user['role'], ['admin', 'moderator'], true)): ?>
  <p class="notice notice-warning">You have administrator access. Please <a class="text-link" href="<?= $view->url('account/security/two-factor/') ?>">turn on two-factor authentication</a>.</p>
<?php endif; ?>
