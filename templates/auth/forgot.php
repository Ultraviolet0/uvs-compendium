<?php
/**
 * @var \Uvs\Http\View $view
 * @var \Uvs\Security\Turnstile\Turnstile $turnstile
 * @var bool $mailEnabled
 */
$view->partial('breadcrumbs', ['trail' => [['Sign in', 'account/login/'], ['Reset password', null]]]);
?>
<div class="auth-layout auth-layout-narrow">
  <section class="section-panel auth-panel flow-lg" aria-labelledby="page-title">
    <div class="flow">
      <p class="eyebrow">Account recovery</p>
      <h1 id="page-title">Reset your password</h1>
    </div>
    <?php if (!$mailEnabled || !$turnstile->isAvailable()): ?>
      <p class="notice notice-info">Password reset by email is not available on this site right now. Please contact the site administrator.</p>
    <?php else: ?>
      <p>Enter the email address for your account. If it matches an eligible account, we will send a link that works for one hour.</p>
      <?php $view->partial('error-summary', ['fields' => ['email']]); ?>
      <form class="form-stack" method="post" action="<?= $view->url('account/password/forgot/') ?>">
        <?= $view->csrf() ?>
        <div class="form-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" required maxlength="254" autocomplete="email" value="<?= h($view->old('email')) ?>">
        </div>
        <?php $view->partial('turnstile', ['turnstile' => $turnstile, 'action' => 'password_reset']); ?>
        <div class="form-actions">
          <button class="button button-primary" type="submit">Send reset link</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>
