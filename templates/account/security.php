<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var \Uvs\Auth\PasswordPolicy $policy
 * @var int $recoveryLeft
 */
$view->partial('breadcrumbs', ['trail' => [['Your account', 'account/'], ['Security', null]]]);
$mfa = $user['mfa_enabled_at'] !== null;
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <div class="flow">
    <p class="eyebrow">Your account</p>
    <h1 id="page-title">Security</h1>
  </div>
  <?php $view->partial('account-nav', ['active' => 'security']); ?>
</section>

<?php $view->partial('error-summary', ['fields' => []]); ?>

<div class="two-column">
  <section class="section-panel flow" aria-labelledby="password-title">
    <h2 id="password-title">Change password</h2>
    <form class="form-stack" method="post" action="<?= $view->url('account/security/password/') ?>">
      <?= $view->csrf() ?>
      <div class="form-field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password"<?= $view->invalid('current_password') ?>>
        <?= $view->fieldError('current_password') ?>
      </div>
      <div class="form-field">
        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" required minlength="<?= $policy->minLength() ?>" maxlength="<?= $policy->maxLength() ?>" autocomplete="new-password"<?= $view->invalid('new_password', 'new-password-help') ?>>
        <p class="field-help" id="new-password-help">At least <?= $policy->minLength() ?> characters. Your other sessions will be signed out.</p>
        <?= $view->fieldError('new_password') ?>
      </div>
      <div class="form-field">
        <label for="new_password_confirmation">Confirm new password</label>
        <input id="new_password_confirmation" name="new_password_confirmation" type="password" required autocomplete="new-password"<?= $view->invalid('new_password_confirmation') ?>>
        <?= $view->fieldError('new_password_confirmation') ?>
      </div>
      <button class="button button-primary" type="submit">Change password</button>
    </form>
  </section>

  <section class="section-panel flow" aria-labelledby="email-title">
    <h2 id="email-title">Email address</h2>
    <p>Current address: <strong><?= h((string) $user['email']) ?></strong>. Only you and site administrators can see it.</p>
    <form class="form-stack" method="post" action="<?= $view->url('account/security/email/') ?>">
      <?= $view->csrf() ?>
      <div class="form-field">
        <label for="email">New email address</label>
        <input id="email" name="email" type="email" required maxlength="254" autocomplete="email"<?= $view->invalid('email') ?>>
        <?= $view->fieldError('email') ?>
      </div>
      <div class="form-field">
        <label for="email_password">Current password</label>
        <input id="email_password" name="email_password" type="password" required autocomplete="current-password"<?= $view->invalid('email_password') ?>>
        <?= $view->fieldError('email_password') ?>
      </div>
      <button class="button button-secondary" type="submit">Update email</button>
    </form>
  </section>
</div>

<section class="section-panel flow" aria-labelledby="mfa-title">
  <div class="section-heading-row">
    <h2 id="mfa-title">Two-factor authentication</h2>
    <?php $view->partial('status-badge', ['label' => $mfa ? 'On' : 'Off', 'tone' => $mfa ? 'success' : 'muted']); ?>
  </div>
  <?php if (!$mfa): ?>
    <p>Add a second step to sign-in with an authenticator app such as Aegis, 2FAS, Google Authenticator, or 1Password.<?= in_array($user['role'], ['admin', 'moderator'], true) ? ' <strong>Strongly recommended for administrators.</strong>' : '' ?></p>
    <p><a class="button button-primary" href="<?= $view->url('account/security/two-factor/') ?>">Set up two-factor authentication</a></p>
  <?php else: ?>
    <p>Enabled on <?= $view->time((string) $user['mfa_enabled_at']) ?>. You have <strong><?= $recoveryLeft ?></strong> unused recovery code<?= $recoveryLeft === 1 ? '' : 's' ?>.</p>
    <div class="two-column">
      <form class="form-stack card" method="post" action="<?= $view->url('account/security/two-factor/recovery-codes/') ?>">
        <?= $view->csrf() ?>
        <h3>New recovery codes</h3>
        <p class="field-help">Generating new codes invalidates the old ones.</p>
        <div class="form-field">
          <label for="codes_password">Current password</label>
          <input id="codes_password" name="codes_password" type="password" required autocomplete="current-password"<?= $view->invalid('codes_password') ?>>
          <?= $view->fieldError('codes_password') ?>
        </div>
        <button class="button button-secondary" type="submit">Generate new codes</button>
      </form>
      <form class="form-stack card" method="post" action="<?= $view->url('account/security/two-factor/disable/') ?>" data-confirm="Turn off two-factor authentication?">
        <?= $view->csrf() ?>
        <h3>Turn off</h3>
        <div class="form-field">
          <label for="disable_password">Current password</label>
          <input id="disable_password" name="disable_password" type="password" required autocomplete="current-password"<?= $view->invalid('disable_password') ?>>
          <?= $view->fieldError('disable_password') ?>
        </div>
        <div class="form-field">
          <label for="disable_code">Authentication or recovery code</label>
          <input id="disable_code" name="disable_code" type="text" required autocomplete="one-time-code" autocapitalize="none"<?= $view->invalid('disable_code') ?>>
          <?= $view->fieldError('disable_code') ?>
        </div>
        <button class="button button-danger" type="submit">Turn off two-factor</button>
      </form>
    </div>
  <?php endif; ?>
</section>
