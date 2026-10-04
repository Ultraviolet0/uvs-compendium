<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $guide
 * @var array<string, string> $appliesTo
 * @var int $maxBody
 * @var bool $slugLocked
 */
$view->partial('admin-header', ['section' => 'guides', 'title' => 'Edit: ' . $guide['title'], 'trail' => [['Guides', 'admin/guides/'], [(string) $guide['title'], 'admin/guides/' . (int) $guide['id'] . '/'], ['Edit', null]]]);
?>
<p class="notice notice-info">You are editing the working copy. Saving records an administrator revision; readers only see changes after you publish.</p>
<?php $view->partial('error-summary', ['fields' => ['title', 'summary', 'body', 'slug', 'note']]); ?>
<form id="guide-form" class="form-stack editor-form section-panel" method="post" action="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/edit/') ?>"
  data-preview-url="<?= $view->url('account/guides/preview/') ?>" data-guide-id="<?= (int) $guide['id'] ?>">
  <?= $view->csrf() ?>
  <input type="hidden" name="lock_version" value="<?= (int) $guide['lock_version'] ?>">
  <div class="form-grid form-grid-wide">
    <div class="form-field">
      <label for="title">Title</label>
      <input id="title" name="title" type="text" required maxlength="140" value="<?= h($view->old('title')) ?>"<?= $view->invalid('title') ?>>
      <?= $view->fieldError('title') ?>
    </div>
    <div class="form-field">
      <label for="slug">URL slug</label>
      <input id="slug" name="slug" type="text" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= h($view->old('slug')) ?>"<?= $slugLocked ? ' readonly' : '' ?><?= $view->invalid('slug', 'slug-help') ?>>
      <p class="field-help" id="slug-help"><?= $slugLocked ? 'Fixed because this guide has been published.' : 'Lowercase words separated by hyphens. Cannot match an existing guide or reserved path.' ?></p>
      <?= $view->fieldError('slug') ?>
    </div>
  </div>
  <div class="form-field">
    <label for="applies_to">Applies to</label>
    <select id="applies_to" name="applies_to">
      <option value="">Not specified</option>
      <?php foreach ($appliesTo as $value => $label): ?>
        <option value="<?= h($value) ?>"<?= $view->old('applies_to') === $value ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-field">
    <label for="summary">Summary</label>
    <textarea id="summary" name="summary" rows="2" maxlength="400"<?= $view->invalid('summary') ?>><?= h($view->old('summary')) ?></textarea>
    <?= $view->fieldError('summary') ?>
  </div>
  <div class="form-field editor-field">
    <div class="editor-label-row">
      <label for="body">Guide text (Markdown)</label>
      <div class="editor-modes" role="group" aria-label="Editor view" hidden data-editor-modes>
        <button type="button" class="editor-mode" data-mode="write" aria-pressed="true">Write</button>
        <button type="button" class="editor-mode" data-mode="preview" aria-pressed="false">Preview</button>
        <button type="button" class="editor-mode editor-mode-split" data-mode="split" aria-pressed="false">Side by side</button>
      </div>
    </div>
    <div class="editor-shell" data-editor-shell data-mode="write">
      <div class="editor-panes">
        <textarea id="body" name="body" class="editor-textarea" rows="24" maxlength="<?= $maxBody ?>"<?= $view->invalid('body') ?>><?= h($view->old('body')) ?></textarea>
        <div class="editor-preview guide-content community-guide-content" id="editor-preview" tabindex="0" aria-label="Preview" hidden></div>
      </div>
    </div>
    <?= $view->fieldError('body') ?>
  </div>
  <div class="form-field">
    <label for="note">Revision note <span class="optional">(optional, internal)</span></label>
    <input id="note" name="note" type="text" maxlength="500" value="<?= h($view->old('note')) ?>">
  </div>
  <p class="editor-status" role="status" aria-live="polite" data-editor-status></p>
  <div class="form-actions">
    <button class="button button-primary" type="submit">Save revision</button>
    <a class="text-link" href="<?= $view->url('admin/guides/' . (int) $guide['id'] . '/') ?>">Cancel</a>
  </div>
</form>
