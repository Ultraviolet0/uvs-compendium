<?php
/**
 * @var \Uvs\Http\View $view
 * @var bool $valid
 * @var \Uvs\Auth\PasswordPolicy $policy
 */
$view->partial('breadcrumbs', ['trail' => [['Sign in', 'account/login/'], ['New password', null]]]);
?>
<div class="auth-layout auth-layout-narrow">
  <section class="section-panel auth-panel flow-lg" aria-labelledby="page-title">
    <div class="flow">
      <p class="eyebrow">Account recovery</p>
      <h1 id="page-title">Choose a new password</h1>
    </div>
    <?php if (!$valid): ?>
      <p class="notice notice-warning">This reset link is invalid, has expired, or was already used.</p>
      <p><a class="button button-secondary" href="<?= $view->url('account/password/forgot/') ?>">Request a new link</a></p>
    <?php else: ?>
      <?php $view->partial('error-summary', ['fields' => ['password', 'password_confirmation']]); ?>
      <form class="form-stack" method="post" action="<?= $view->url('account/password/reset/') ?>">
        <?= $view->csrf() ?>
        <div class="form-field">
          <label for="password">New password</label>
          <input id="password" name="password" type="password" required minlength="<?= $policy->minLength() ?>"
            maxlength="<?= $policy->maxLength() ?>" autocomplete="new-password"<?= $view->invalid('password', 'password-help') ?>>
          <p class="field-help" id="password-help">At least <?= $policy->minLength() ?> characters. Other signed-in sessions will be signed out.</p>
          <?= $view->fieldError('password') ?>
        </div>
        <div class="form-field">
          <label for="password_confirmation">Confirm new password</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"<?= $view->invalid('password_confirmation') ?>>
          <?= $view->fieldError('password_confirmation') ?>
        </div>
        <div class="form-actions">
          <button class="button button-primary" type="submit">Change password</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>
