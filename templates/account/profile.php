<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var array<string, string> $games
 * @var array{max_upload: int, quota: int, per_guide: int} $limits
 * @var int $usage
 */
use Uvs\Media\ImageProcessor;
use Uvs\Support\Text;

$view->partial('breadcrumbs', ['trail' => [['Your account', 'account/'], ['Profile', null]]]);
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <div class="flow">
    <p class="eyebrow">Your account</p>
    <h1 id="page-title">Profile</h1>
    <p>Everything here is optional and appears on your public profile<?= $user['status'] === 'active' ? '' : ' once your account is approved' ?>. Your email address is never shown.</p>
  </div>
  <?php $view->partial('account-nav', ['active' => 'profile']); ?>
</section>

<?php $view->partial('account-status', ['user' => $user]); ?>

<div class="two-column">
  <section class="section-panel flow" aria-labelledby="avatar-title">
    <h2 id="avatar-title">Avatar</h2>
    <div class="avatar-editor">
      <?php uvs_avatar($user, 'xl', false); ?>
      <div class="flow">
        <form class="form-stack" method="post" action="<?= $view->url('account/profile/avatar/') ?>" enctype="multipart/form-data">
          <?= $view->csrf() ?>
          <div class="form-field">
            <label for="avatar">Upload a new avatar</label>
            <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required<?= $view->invalid('avatar', 'avatar-help') ?>>
            <p class="field-help" id="avatar-help">JPEG, PNG, WebP, or GIF up to <?= h(ImageProcessor::megabytes($limits['max_upload'])) ?>. It is cropped to a square, resized, and stripped of metadata.</p>
            <?= $view->fieldError('avatar') ?>
          </div>
          <button class="button button-primary" type="submit">Upload avatar</button>
        </form>
        <?php if (!empty($user['avatar_public_id'])): ?>
          <form method="post" action="<?= $view->url('account/profile/avatar/delete/') ?>" data-confirm="Remove your avatar?">
            <?= $view->csrf() ?>
            <button class="button button-quiet" type="submit">Remove avatar</button>
          </form>
        <?php endif; ?>
        <p class="field-help">Image storage used: <?= h(Text::bytes($usage)) ?> of <?= h(ImageProcessor::megabytes($limits['quota'])) ?>.</p>
      </div>
    </div>
  </section>

  <section class="section-panel flow" aria-labelledby="details-title">
    <h2 id="details-title">Details</h2>
    <?php $view->partial('error-summary', ['fields' => ['bio', 'preferred_game', 'website_url', 'discord_handle']]); ?>
    <form class="form-stack" method="post" action="<?= $view->url('account/profile/') ?>">
      <?= $view->csrf() ?>
      <div class="form-field">
        <label for="bio">Short bio</label>
        <textarea id="bio" name="bio" rows="5" maxlength="1000"<?= $view->invalid('bio', 'bio-counter') ?>><?= h($view->old('bio')) ?></textarea>
        <p class="field-help" id="bio-counter" data-counter-for="bio" aria-live="polite"></p>
        <?= $view->fieldError('bio') ?>
      </div>
      <div class="form-field">
        <label for="preferred_game">Preferred game</label>
        <select id="preferred_game" name="preferred_game"<?= $view->invalid('preferred_game') ?>>
          <option value="">Not specified</option>
          <?php foreach ($games as $value => $label): ?>
            <option value="<?= h($value) ?>"<?= $view->old('preferred_game') === $value ? ' selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $view->fieldError('preferred_game') ?>
      </div>
      <div class="form-field">
        <label for="website_url">Website</label>
        <input id="website_url" name="website_url" type="url" maxlength="200" inputmode="url" placeholder="https://"
          value="<?= h($view->old('website_url')) ?>"<?= $view->invalid('website_url') ?>>
        <?= $view->fieldError('website_url') ?>
      </div>
      <div class="form-field">
        <label for="discord_handle">Discord name</label>
        <input id="discord_handle" name="discord_handle" type="text" maxlength="40" autocapitalize="none" spellcheck="false"
          value="<?= h($view->old('discord_handle')) ?>"<?= $view->invalid('discord_handle', 'discord-help') ?>>
        <p class="field-help" id="discord-help">Shown as plain text so other players can find you.</p>
        <?= $view->fieldError('discord_handle') ?>
      </div>
      <div class="form-actions">
        <button class="button button-primary" type="submit">Save profile</button>
        <?php if ($user['status'] === 'active'): ?>
          <a class="text-link" href="<?= $view->url('members/' . rawurlencode((string) $user['username']) . '/') ?>">View public profile</a>
        <?php endif; ?>
      </div>
    </form>
  </section>
</div>
