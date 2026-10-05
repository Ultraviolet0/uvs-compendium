<?php
/**
 * @var \Uvs\Http\View $view
 * @var ?string $closed
 * @var string $timer
 * @var \Uvs\Security\Turnstile\Turnstile $turnstile
 * @var \Uvs\Auth\PasswordPolicy $policy
 * @var string $honeypot
 * @var bool $autoApprove
 */
$view->partial('breadcrumbs', ['trail' => [['Create an account', null]]]);
?>
<div class="auth-layout">
  <section class="section-panel auth-panel flow-lg" aria-labelledby="page-title">
    <div class="flow">
      <p class="eyebrow">Join the compendium</p>
      <h1 id="page-title">Create an account</h1>
      <p class="hero-copy">Members can build a profile, list their Diablo characters, and submit guides for review.</p>
    </div>

    <?php if ($closed !== null): ?>
      <p class="notice notice-info"><?= h($closed) ?></p>
      <p>Already a member? <a class="text-link" href="<?= $view->url('account/login/') ?>">Sign in</a>.</p>
    <?php else: ?>
      <?php $view->partial('error-summary', ['fields' => ['username', 'email', 'password', 'password_confirmation', 'accept_privacy']]); ?>
      <?php if ($view->error('form') === null && $view->errors() === []): ?>
        <p class="field-help"><?= $autoApprove
          ? 'New accounts are currently activated automatically.'
          : 'New accounts are reviewed by an administrator before they can submit guides. You can sign in and set up your profile while you wait.' ?></p>
      <?php endif; ?>

      <form class="form-stack" method="post" action="<?= $view->url('account/signup/') ?>">
        <?= $view->csrf() ?>
        <input type="hidden" name="form_started" value="<?= h($timer) ?>">

        <div class="form-field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required minlength="3" maxlength="24"
            pattern="[A-Za-z0-9][A-Za-z0-9_\-]{2,23}" autocomplete="username" autocapitalize="none" spellcheck="false"
            value="<?= h($view->old('username')) ?>"<?= $view->invalid('username', 'username-help') ?>>
          <p class="field-help" id="username-help">3–24 letters, numbers, underscores, or hyphens. Shown publicly; capitalisation is kept.</p>
          <?= $view->fieldError('username') ?>
        </div>

        <div class="form-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" required maxlength="254" autocomplete="email"
            value="<?= h($view->old('email')) ?>"<?= $view->invalid('email', 'email-help') ?>>
          <p class="field-help" id="email-help">Private. Used only for account messages such as password resets.</p>
          <?= $view->fieldError('email') ?>
        </div>

        <div class="form-field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required minlength="<?= $policy->minLength() ?>"
            maxlength="<?= $policy->maxLength() ?>" autocomplete="new-password"<?= $view->invalid('password', 'password-help') ?>>
          <p class="field-help" id="password-help">At least <?= $policy->minLength() ?> characters. Passphrases are encouraged.</p>
          <?= $view->fieldError('password') ?>
        </div>

        <div class="form-field">
          <label for="password_confirmation">Confirm password</label>
          <input id="password_confirmation" name="password_confirmation" type="password" required
            maxlength="<?= $policy->maxLength() ?>" autocomplete="new-password"<?= $view->invalid('password_confirmation') ?>>
          <?= $view->fieldError('password_confirmation') ?>
        </div>

        <div class="form-honeypot">
          <label for="<?= h($honeypot) ?>">Leave this field empty</label>
          <input id="<?= h($honeypot) ?>" name="<?= h($honeypot) ?>" type="text" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-field form-check">
          <input id="accept_privacy" name="accept_privacy" type="checkbox" value="1" required<?= $view->invalid('accept_privacy') ?>>
          <label for="accept_privacy">I have read the <a class="text-link" href="<?= $view->url('privacy/') ?>" target="_blank" rel="noopener">privacy notice<span class="sr-only"> (opens in a new tab)</span></a>.</label>
          <?= $view->fieldError('accept_privacy') ?>
        </div>

        <?php $view->partial('turnstile', ['turnstile' => $turnstile, 'action' => 'signup']); ?>

        <div class="form-actions">
          <button class="button button-primary" type="submit">Create account</button>
        </div>
      </form>
      <p>Already have an account? <a class="text-link" href="<?= $view->url('account/login/') ?>">Sign in</a>.</p>
    <?php endif; ?>
  </section>

  <aside class="card auth-aside flow" aria-labelledby="membership-title">
    <p class="card-label">Membership</p>
    <h2 id="membership-title">What members can do</h2>
    <ul class="check-list">
      <li>Write guides with a live preview and submit them for review.</li>
      <li>Show an avatar, a short bio, and the characters you play.</li>
      <li>Get credited on every published guide.</li>
    </ul>
    <p class="text-muted">The calculators and existing guides stay free and public — no account needed.</p>
  </aside>
</div>
