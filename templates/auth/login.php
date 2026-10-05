<?php
/**
 * @var \Uvs\Http\View $view
 * @var ?string $next
 * @var bool $mailEnabled
 */
$view->partial('breadcrumbs', ['trail' => [['Sign in', null]]]);
?>
<div class="auth-layout auth-layout-narrow">
  <section class="section-panel auth-panel flow-lg" aria-labelledby="page-title">
    <div class="flow">
      <p class="eyebrow">Members</p>
      <h1 id="page-title">Sign in</h1>
    </div>
    <?php $view->partial('error-summary', ['fields' => []]); ?>
    <form class="form-stack" method="post" action="<?= $view->url('account/login/') ?>">
      <?= $view->csrf() ?>
      <?php if ($next !== null): ?><input type="hidden" name="next" value="/<?= h($next) ?>"><?php endif; ?>
      <div class="form-field">
        <label for="identifier">Username or email</label>
        <input id="identifier" name="identifier" type="text" required maxlength="254" autocomplete="username"
          autocapitalize="none" spellcheck="false" value="<?= h($view->old('identifier')) ?>">
      </div>
      <div class="form-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required maxlength="1024" autocomplete="current-password">
      </div>
      <div class="form-actions">
        <button class="button button-primary" type="submit">Sign in</button>
        <?php if ($mailEnabled): ?>
          <a class="text-link" href="<?= $view->url('account/password/forgot/') ?>">Forgot your password?</a>
        <?php endif; ?>
      </div>
    </form>
    <p>New here? <a class="text-link" href="<?= $view->url('account/signup/') ?>">Create an account</a>.</p>
  </section>
</div>
