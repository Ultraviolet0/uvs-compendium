<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var ?array<string, mixed> $guide
 * @var ?array{label: string, tone: string, detail: string} $status
 * @var bool $canEdit
 * @var bool $canSubmit
 * @var bool $canWithdraw
 * @var bool $canDelete
 * @var bool $submissionsOpen
 * @var list<array<string, mixed>> $media
 * @var array{max_upload: int, quota: int, per_guide: int} $limits
 * @var int $usage
 * @var array<string, string> $appliesTo
 * @var int $maxBody
 */
use Uvs\Media\ImageProcessor;
use Uvs\Support\Text;

$isNew = $guide === null;
$guideId = $isNew ? 0 : (int) $guide['id'];
$title = $isNew ? 'Write a guide' : (string) $guide['title'];
$action = $isNew ? $view->url('account/guides/new/') : $view->url('account/guides/' . $guideId . '/save/');
$view->partial('breadcrumbs', ['trail' => [['Your account', 'account/'], ['Guides', 'account/guides/'], [$isNew ? 'New guide' : $title, null]]]);
?>
<section class="section-panel flow editor-header" aria-labelledby="page-title">
  <p class="eyebrow">Guide editor</p>
  <h1 id="page-title" class="editor-title"><?= h($title) ?></h1>
  <?php if ($status !== null): ?>
    <div class="editor-state">
      <?php $view->partial('status-badge', ['label' => $status['label'], 'tone' => $status['tone']]); ?>
      <p><?= h($status['detail']) ?></p>
    </div>
    <p class="field-help">Web address once published: <code><?= h('/guides/' . $guide['slug'] . '/') ?></code><?= $guide['first_published_at'] === null ? ' (follows the title until first publication)' : '' ?></p>
  <?php else: ?>
    <p>Drafts are private. Save as often as you like, preview the result, and submit when it is ready for an administrator to review.</p>
  <?php endif; ?>
  <?php if (!$isNew && !empty($guide['moderation_note']) && in_array($guide['review_status'], ['needs_changes', 'rejected', 'approved'], true)): ?>
    <div class="moderation-note" role="note">
      <p class="moderation-note-title">Note from the administrator</p>
      <p><?= nl2br(h((string) $guide['moderation_note'])) ?></p>
    </div>
  <?php endif; ?>
  <?php if (!$isNew): ?>
    <p class="button-row">
      <a class="button button-secondary button-small" href="<?= $view->url('account/guides/' . $guideId . '/preview/') ?>">Full-page preview</a>
      <?php if ($guide['visibility'] === 'published'): ?>
        <a class="button button-secondary button-small" href="<?= $view->url('guides/' . $guide['slug'] . '/') ?>">View published version</a>
      <?php endif; ?>
    </p>
  <?php endif; ?>
</section>

<?php $view->partial('error-summary', ['fields' => ['title', 'summary', 'body', 'applies_to']]); ?>

<?php if (!$canEdit): ?>
  <section class="section-panel flow" aria-labelledby="locked-title">
    <h2 id="locked-title">Editing is locked</h2>
    <p><?= $guide['review_status'] === 'rejected'
      ? 'This submission was not accepted, so it can no longer be edited.'
      : 'This guide is with an administrator. Withdraw it to make more changes.' ?></p>
    <?php if ($canWithdraw): ?>
      <form method="post" action="<?= $view->url('account/guides/' . $guideId . '/withdraw/') ?>" data-confirm="Withdraw this guide from review?">
        <?= $view->csrf() ?>
        <input type="hidden" name="lock_version" value="<?= (int) $guide['lock_version'] ?>">
        <button class="button button-secondary" type="submit">Withdraw from review</button>
      </form>
    <?php endif; ?>
  </section>
<?php else: ?>
  <form id="guide-form" class="form-stack editor-form" method="post" action="<?= $action ?>"
    <?php if (!$isNew): ?>data-autosave-url="<?= $action ?>"<?php endif; ?>
    data-preview-url="<?= $view->url('account/guides/preview/') ?>"
    data-upload-url="<?= $isNew ? '' : $view->url('account/guides/' . $guideId . '/media/') ?>"
    data-guide-id="<?= $guideId ?>">
    <?= $view->csrf() ?>
    <?php if (!$isNew): ?><input type="hidden" name="lock_version" value="<?= (int) $guide['lock_version'] ?>"><?php endif; ?>

    <div class="form-grid form-grid-wide">
      <div class="form-field">
        <label for="title">Title</label>
        <input id="title" name="title" type="text" required maxlength="140" value="<?= h($view->old('title')) ?>"<?= $view->invalid('title') ?>>
        <?= $view->fieldError('title') ?>
      </div>
      <div class="form-field">
        <label for="applies_to">Applies to <span class="optional">(optional)</span></label>
        <select id="applies_to" name="applies_to"<?= $view->invalid('applies_to') ?>>
          <option value="">Not specified</option>
          <?php foreach ($appliesTo as $value => $label): ?>
            <option value="<?= h($value) ?>"<?= $view->old('applies_to') === $value ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-field">
      <label for="summary">Summary</label>
      <textarea id="summary" name="summary" rows="2" maxlength="400"<?= $view->invalid('summary', 'summary-help summary-counter') ?>><?= h($view->old('summary')) ?></textarea>
      <p class="field-help" id="summary-help">One or two sentences shown on the guide list and under the title.</p>
      <p class="field-help counter" id="summary-counter" data-counter-for="summary" aria-live="polite"></p>
      <?= $view->fieldError('summary') ?>
    </div>

    <div class="form-field editor-field">
      <div class="editor-label-row">
        <label for="body" id="body-label">Guide text (Markdown)</label>
        <div class="editor-modes" role="group" aria-label="Editor view" hidden data-editor-modes>
          <button type="button" class="editor-mode" data-mode="write" aria-pressed="true">Write</button>
          <button type="button" class="editor-mode" data-mode="preview" aria-pressed="false">Preview</button>
          <button type="button" class="editor-mode editor-mode-split" data-mode="split" aria-pressed="false">Side by side</button>
        </div>
      </div>
      <div class="editor-shell" data-editor-shell data-mode="write">
        <div class="editor-toolbar" role="toolbar" aria-label="Formatting" aria-controls="body" hidden data-editor-toolbar>
          <button type="button" data-action="heading" title="Section heading">H2</button>
          <button type="button" data-action="subheading" title="Subheading">H3</button>
          <button type="button" data-action="bold" title="Bold (Ctrl+B)"><strong>B</strong><span class="sr-only"> Bold</span></button>
          <button type="button" data-action="italic" title="Italic (Ctrl+I)"><em>I</em><span class="sr-only"> Italic</span></button>
          <button type="button" data-action="link" title="Link (Ctrl+K)">Link</button>
          <button type="button" data-action="bullets" title="Bulleted list">• List</button>
          <button type="button" data-action="numbers" title="Numbered list">1. List</button>
          <button type="button" data-action="quote" title="Quotation">Quote</button>
          <button type="button" data-action="code" title="Code or formula block">Code</button>
          <button type="button" data-action="callout" title="Tip, note, or warning box">Callout</button>
          <button type="button" data-action="table" title="Table">Table</button>
          <button type="button" data-action="image" title="Insert an uploaded image"<?= $isNew ? ' disabled' : '' ?>>Image</button>
          <button type="button" data-action="youtube" title="Embed a YouTube video">YouTube</button>
        </div>
        <div class="editor-panes">
          <textarea id="body" name="body" class="editor-textarea" rows="22" maxlength="<?= $maxBody ?>" spellcheck="true"
            <?= $view->invalid('body', 'body-help body-counter') ?>><?= h($view->old('body')) ?></textarea>
          <div class="editor-preview guide-content community-guide-content" id="editor-preview" tabindex="0" aria-label="Preview" hidden></div>
        </div>
      </div>
      <p class="field-help" id="body-help">Use <code>##</code> for sections. Raw HTML is shown as text. <?= $isNew ? 'Save the draft once to start uploading images.' : '' ?></p>
      <p class="field-help counter" id="body-counter" data-counter-for="body" aria-live="polite"></p>
      <?= $view->fieldError('body') ?>
    </div>

    <p class="editor-status" role="status" aria-live="polite" data-editor-status></p>

    <div class="form-actions editor-actions">
      <button class="button button-primary" type="submit" name="intent" value="save"><?= $isNew ? 'Create draft' : 'Save draft' ?></button>
      <?php if ($canSubmit && !$isNew): ?>
        <button class="button button-secondary" type="submit" name="intent" value="submit"<?= $submissionsOpen ? '' : ' disabled' ?>
          data-confirm="Submit this guide for review? You will not be able to edit it while it is being reviewed.">Save and submit for review</button>
      <?php endif; ?>
    </div>
    <?php if (!$submissionsOpen && !$isNew): ?>
      <p class="notice notice-info">Guide submissions are paused right now. Your drafts are safe.</p>
    <?php endif; ?>
  </form>

  <details class="section-panel markdown-help">
    <summary>Formatting help</summary>
    <div class="markdown-help-grid">
      <div><h3>Text</h3><pre><code>## Section heading
### Subheading
**bold**, _italic_, ~~struck~~
[link text](https://example.com)</code></pre></div>
      <div><h3>Lists and quotes</h3><pre><code>- bullet
1. numbered
> quoted text</code></pre></div>
      <div><h3>Callouts</h3><pre><code>> [!TIP]
> Shop at clvl 30 for ...
(also [!NOTE] and [!WARNING])</code></pre></div>
      <div><h3>Media</h3><pre><code>![Alt text](media:IMAGE_ID)

```youtube
https://youtu.be/VIDEO_ID
Optional caption
```</code></pre></div>
    </div>
  </details>
<?php endif; ?>

<?php if (!$isNew): ?>
  <section class="section-panel flow" id="guide-images" aria-labelledby="images-title">
    <h2 id="images-title">Images <span class="count-pill"><?= count($media) ?> / <?= (int) $limits['per_guide'] ?></span></h2>
    <p class="field-help">JPEG, PNG, WebP, or GIF up to <?= h(ImageProcessor::megabytes($limits['max_upload'])) ?>. Images are resized, compressed, and stripped of metadata. Your storage: <?= h(Text::bytes($usage)) ?> of <?= h(ImageProcessor::megabytes($limits['quota'])) ?>. Images not used in the guide are removed after a few days.</p>
    <?php if ($canEdit && count($media) < $limits['per_guide']): ?>
      <form class="form-grid upload-form" method="post" action="<?= $view->url('account/guides/' . $guideId . '/media/') ?>" enctype="multipart/form-data" data-upload-form>
        <?= $view->csrf() ?>
        <div class="form-field">
          <label for="image">Image file</label>
          <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </div>
        <div class="form-field">
          <label for="alt">Description (alt text)</label>
          <input id="alt" name="alt" type="text" maxlength="200" aria-describedby="alt-help">
          <p class="field-help" id="alt-help">Describe what the image shows for readers who cannot see it.</p>
        </div>
        <div class="form-field form-field-end">
          <button class="button button-secondary" type="submit">Upload image</button>
        </div>
      </form>
    <?php endif; ?>
    <?php if ($media === []): ?>
      <p class="text-muted" data-media-empty>No images uploaded yet.</p>
    <?php endif; ?>
    <ul class="media-grid" data-media-list>
      <?php foreach ($media as $item): $markdown = '![' . ($item['alt_text'] ?? 'Image') . '](media:' . $item['public_id'] . ')'; ?>
        <li class="media-card" data-markdown="<?= h($markdown) ?>">
          <img src="<?= $view->mediaUrl((string) $item['public_id'], (string) $item['extension']) ?>" alt="<?= h((string) ($item['alt_text'] ?? '')) ?>" width="<?= (int) $item['width'] ?>" height="<?= (int) $item['height'] ?>" loading="lazy">
          <code class="media-code"><?= h($markdown) ?></code>
          <div class="media-actions">
            <?php if ($canEdit): ?>
              <button class="button button-quiet button-small" type="button" data-insert-media hidden>Insert</button>
            <?php endif; ?>
            <form method="post" action="<?= $view->url('account/media/' . $item['public_id'] . '/delete/') ?>" data-confirm="Delete this image? Remove it from the guide text too. Images shown in the published version, or in a version waiting for review, cannot be deleted.">
              <?= $view->csrf() ?>
              <button class="button button-danger button-small" type="submit">Delete<span class="sr-only"> image <?= h((string) ($item['alt_text'] ?? '')) ?></span></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($canDelete): ?>
  <section class="section-panel flow danger-zone" id="delete-guide" aria-labelledby="delete-title">
    <h2 id="delete-title">Delete this guide</h2>
    <p>This permanently removes the draft, its history, and its images. It cannot be undone.</p>
    <form class="form-stack" method="post" action="<?= $view->url('account/guides/' . $guideId . '/delete/') ?>">
      <?= $view->csrf() ?>
      <input type="hidden" name="lock_version" value="<?= (int) $guide['lock_version'] ?>">
      <div class="form-check">
        <input id="confirm-delete" name="confirm" type="checkbox" value="1" required>
        <label for="confirm-delete">I understand that “<?= h($title) ?>” will be deleted permanently.</label>
      </div>
      <button class="button button-danger" type="submit">Delete guide</button>
    </form>
  </section>
<?php endif; ?>

<dialog class="editor-dialog" id="link-dialog" aria-labelledby="link-dialog-title">
  <form method="dialog" class="form-stack">
    <h2 id="link-dialog-title">Insert link</h2>
    <div class="form-field"><label for="link-text">Link text</label><input id="link-text" type="text" maxlength="200"></div>
    <div class="form-field"><label for="link-url">Web address</label><input id="link-url" type="url" placeholder="https://" maxlength="500"></div>
    <p class="field-error" data-dialog-error hidden></p>
    <div class="form-actions"><button class="button button-primary" value="insert">Insert link</button><button class="button button-quiet" value="cancel" formnovalidate>Cancel</button></div>
  </form>
</dialog>

<dialog class="editor-dialog" id="youtube-dialog" aria-labelledby="youtube-dialog-title">
  <form method="dialog" class="form-stack">
    <h2 id="youtube-dialog-title">Embed a YouTube video</h2>
    <div class="form-field"><label for="youtube-url">YouTube link</label><input id="youtube-url" type="url" placeholder="https://www.youtube.com/watch?v=…" maxlength="300" aria-describedby="youtube-help"><p class="field-help" id="youtube-help">Videos play through YouTube’s privacy-enhanced mode. Other sites and embed codes are not supported.</p></div>
    <div class="form-field"><label for="youtube-caption">Caption <span class="optional">(optional)</span></label><input id="youtube-caption" type="text" maxlength="200"></div>
    <p class="field-error" data-dialog-error hidden></p>
    <div class="form-actions"><button class="button button-primary" value="insert">Insert video</button><button class="button button-quiet" value="cancel" formnovalidate>Cancel</button></div>
  </form>
</dialog>

<dialog class="editor-dialog" id="image-dialog" aria-labelledby="image-dialog-title">
  <form method="dialog" class="form-stack">
    <h2 id="image-dialog-title">Insert an image</h2>
    <div class="form-field"><label for="image-dialog-file">Upload a new image</label><input id="image-dialog-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
    <div class="form-field"><label for="image-dialog-alt">Description (alt text)</label><input id="image-dialog-alt" type="text" maxlength="200"></div>
    <p class="field-help">Or use the <strong>Insert</strong> button beside an image already uploaded below the editor.</p>
    <p class="field-error" data-dialog-error hidden></p>
    <p class="editor-status" role="status" aria-live="polite" data-dialog-status></p>
    <div class="form-actions"><button class="button button-primary" value="insert">Upload and insert</button><button class="button button-quiet" value="cancel" formnovalidate>Cancel</button></div>
  </form>
</dialog>
