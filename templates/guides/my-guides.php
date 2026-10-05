<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $guides
 * @var bool $submissionsOpen
 */
use Uvs\Guides\GuideStatus;

$view->partial('breadcrumbs', ['trail' => [['Your account', 'account/'], ['Guides', null]]]);
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <div class="section-heading-row">
    <div class="flow">
      <p class="eyebrow">Your account</p>
      <h1 id="page-title">Your guides</h1>
    </div>
    <?php if ($view->can('guide.create')): ?>
      <a class="button button-primary" href="<?= $view->url('account/guides/new/') ?>">Write a guide</a>
    <?php endif; ?>
  </div>
  <?php $view->partial('account-nav', ['active' => 'guides']); ?>
</section>

<?php $view->partial('account-status', ['user' => $user]); ?>
<?php if (!$submissionsOpen && $user['status'] === 'active'): ?>
  <p class="notice notice-info">Guide submissions are paused right now. You can keep writing drafts.</p>
<?php endif; ?>

<section class="section-panel flow" aria-labelledby="list-title">
  <h2 id="list-title" class="sr-only">All of your guides</h2>
  <?php if ($guides === []): ?>
    <div class="empty-state">
      <p class="empty-state-title">Nothing here yet</p>
      <p><?= $view->can('guide.create') ? 'Start a draft; it stays private until you submit it.' : 'You can write guides once your account is approved.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap" tabindex="0" role="region" aria-labelledby="list-title">
      <table class="data-table">
        <thead><tr><th scope="col">Guide</th><th scope="col">Status</th><th scope="col">Images</th><th scope="col">Last change</th></tr></thead>
        <tbody>
          <?php foreach ($guides as $guide): $status = GuideStatus::describe($guide); ?>
            <tr>
              <th scope="row" data-label="Guide"><a href="<?= $view->url('account/guides/' . (int) $guide['id'] . '/edit/') ?>"><?= h((string) $guide['title']) ?></a></th>
              <td data-label="Status"><?php $view->partial('status-badge', ['label' => $status['label'], 'tone' => $status['tone']]); ?></td>
              <td data-label="Images"><?= (int) $guide['image_count'] ?></td>
              <td data-label="Last change"><?= $view->time((string) $guide['updated_at'], 'M j, Y') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
