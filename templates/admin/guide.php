<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $guide
 * @var string $html
 * @var list<string> $warnings
 * @var array{label: string, tone: string, detail: string} $status
 * @var list<array<string, mixed>> $revisions
 * @var ?array<string, mixed> $published
 * @var ?list<array{0: string, 1: string}> $changes
 * @var list<string> $actions
 * @var array<string, string> $labels
 * @var list<string> $confirmRequired
 * @var list<array<string, mixed>> $history
 * @var list<array<string, mixed>> $media
 */
use Uvs\Guides\GuideWorkflow;
use Uvs\Support\Text;

$view->partial('admin-header', ['section' => 'guides', 'title' => (string) $guide['title'], 'trail' => [['Guides', 'admin/guides/'], [(string) $guide['title'], null]]]);
$kinds = ['submission' => 'Submitted', 'resubmission' => 'Resubmitted', 'admin_edit' => 'Admin edit', 'publication' => 'Published', 'restore' => 'Restored'];
$noteActions = ['approve', 'approve_publish', 'publish', 'request_changes', 'reject', 'hide'];
?>
<?php $view->partial('error-summary', ['fields' => []]); ?>

<section class="section-panel flow" aria-labelledby="state-title">
  <h2 id="state-title" class="sr-only">State</h2>
  <div class="editor-state">
    <?php $view->partial('status-badge', ['label' => $status['label'], 'tone' => $status['tone']]); ?>
    <p><?= h($status['detail']) ?></p>
  </div>
  <dl class="detail-list detail-list-inline">
    <div><dt>Author</dt><dd><?= $guide['author_username'] !== null ? '<a href="' . $view->url('admin/users/' . (int) $guide['author_id'] . '/') . '">' . h((string) $guide['author_username']) . '</a>' : 'Former member' ?></dd></div>
    <div><dt>URL</dt><dd><code>/guides/<?= h((string) $guide['slug']) ?>/</code><?= $guide['first_published_at'] !== null ? ' (fixed)' : '' ?></dd></div>
    <div><dt>Submitted</dt><dd><?= $guide['submitted_at'] ? h(Text::dateTime((string) $guide['submitted_at'])) : '—' ?></dd></div>
    <div><dt>First published</dt><dd><?= $guide['first_published_at'] ? h(Text::dateTime((string) $guide['first_published_at'])) : '—' ?></dd></div>
    <div><dt>Applies to</dt><dd><?= h(GuideWorkflow::APPLIES_TO[$guide['applies_to'] ?? ''] ?? '—') ?></dd></div>
  </dl>
  <?php if ($guide['moderation_note']): ?>
    <div class="moderation-note"><p class="moderation-note-title">Current note to author</p><p><?= nl2br(h((string) $guide['moderation_note'])) ?></p></div>
  <?php endif; ?>
  <p class="button-row">
    <?php if ($guide['deleted_at'] === null): ?>
      <a class="button button-secondary button-small" href="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/edit/') ?>">Edit working copy</a>
    <?php endif; ?>
    <a class="button button-secondary button-small" href="<?= $view->url('account/guides/' . (int) $guide['id'] . '/preview/') ?>">Full-page preview</a>
    <?php if ($guide['visibility'] === 'published' && $guide['deleted_at'] === null): ?>
      <a class="button button-secondary button-small" href="<?= $view->url('guides/' . $guide['slug'] . '/') ?>">View public page</a>
    <?php endif; ?>
  </p>
</section>

<section class="section-panel flow" aria-labelledby="moderate-title">
  <h2 id="moderate-title">Moderation</h2>
  <div class="action-grid">
    <?php foreach ($actions as $action): $danger = in_array($action, ['reject', 'hide', 'delete', 'purge'], true); ?>
      <form class="form-stack action-form card" method="post" action="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/action/') ?>">
        <?= $view->csrf() ?>
        <input type="hidden" name="action" value="<?= h($action) ?>">
        <input type="hidden" name="lock_version" value="<?= (int) $guide['lock_version'] ?>">
        <h3><?= h($labels[$action]) ?></h3>
        <p class="field-help"><?= h(match ($action) {
          'approve' => 'Mark the current working copy as approved without publishing it yet.',
          'approve_publish' => 'Approve the working copy and make it the public version now.',
          'publish' => 'Make the approved working copy the public version.',
          'request_changes' => 'Send it back to the author with a note. They can revise and resubmit.',
          'reject' => 'Decline this submission. A published version, if any, stays live.',
          'hide' => 'Remove from public view. Reversible with “Restore to public”.',
          'restore' => 'Make the last published version public again.',
          'delete' => 'Soft delete: hidden everywhere, recoverable from the Deleted list.',
          'undelete' => 'Return the guide to the state it had before deletion.',
          'purge' => 'Permanently delete the guide, its history, and its images. This cannot be undone.',
          default => '',
        }) ?></p>
        <?php if (in_array($action, $noteActions, true)): ?>
          <div class="form-field">
            <label for="note-<?= h($action) ?>">Note to author<?= $action === 'request_changes' ? '' : ' <span class="optional">(optional)</span>' ?></label>
            <textarea id="note-<?= h($action) ?>" name="note" rows="3" maxlength="2000"<?= $action === 'request_changes' ? ' required' : '' ?>></textarea>
          </div>
        <?php endif; ?>
        <?php if ($action === 'purge'): ?>
          <div class="form-field">
            <label for="purge-confirmation">Type the slug <code><?= h((string) $guide['slug']) ?></code> to confirm</label>
            <input id="purge-confirmation" name="confirmation" type="text" required autocomplete="off" spellcheck="false">
          </div>
        <?php endif; ?>
        <?php if (in_array($action, $confirmRequired, true)): ?>
          <div class="form-check">
            <input id="confirm-<?= h($action) ?>" name="confirm" type="checkbox" value="1" required>
            <label for="confirm-<?= h($action) ?>">Confirm: <?= h(mb_strtolower($labels[$action])) ?> “<?= h((string) $guide['title']) ?>”</label>
          </div>
        <?php endif; ?>
        <button class="button <?= $danger ? 'button-danger' : 'button-primary' ?>" type="submit"><?= h($labels[$action]) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($changes !== null): ?>
  <section class="section-panel flow" aria-labelledby="changes-title">
    <h2 id="changes-title">Changes since the published version</h2>
    <?php $view->partial('diff', ['operations' => $changes]); ?>
  </section>
<?php endif; ?>

<section class="section-panel flow" aria-labelledby="content-title">
  <h2 id="content-title">Working copy</h2>
  <?php if ($warnings !== []): ?><p class="notice notice-warning"><?= h(implode(' ', $warnings)) ?></p><?php endif; ?>
  <div class="review-preview">
    <p class="review-title"><?= h((string) $guide['title']) ?></p>
    <?php if ($guide['summary'] !== ''): ?><p class="guide-deck"><?= h((string) $guide['summary']) ?></p><?php endif; ?>
    <div class="guide-content community-guide-content"><?= $html ?></div>
  </div>
  <?php if ($media !== []): ?>
    <p class="field-help"><?= count($media) ?> uploaded image<?= count($media) === 1 ? '' : 's' ?>.</p>
  <?php endif; ?>
</section>

<div class="two-column">
  <section class="section-panel flow" aria-labelledby="revisions-title">
    <h2 id="revisions-title">Revisions</h2>
    <?php if ($revisions === []): ?>
      <p class="text-muted">No revisions yet. One is recorded at each submission, administrator edit, and publication.</p>
    <?php else: ?>
      <ol class="history-list">
        <?php foreach ($revisions as $revision): ?>
          <li>
            <a href="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/revisions/' . (int) $revision['id'] . '/') ?>">Revision <?= (int) $revision['revision_number'] ?></a>
            · <?= h($kinds[$revision['kind']] ?? (string) $revision['kind']) ?>
            <?= (int) $revision['id'] === (int) $guide['published_revision_id'] ? '<span class="status-badge status-success">Public</span>' : '' ?>
            <span class="text-muted">by <?= h((string) ($revision['created_by_username'] ?? 'unknown')) ?>, <?= h(Text::dateTime((string) $revision['created_at'])) ?></span>
            <?php if ($revision['note']): ?><p class="field-help"><?= h((string) $revision['note']) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
  <section class="section-panel flow" aria-labelledby="history-title">
    <h2 id="history-title">Moderation history</h2>
    <?php $view->partial('audit-list', ['events' => $history]); ?>
  </section>
</div>
