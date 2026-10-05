<?php
/**
 * @var \Uvs\Http\View $view
 * @var ?string $next
 */
$view->partial('breadcrumbs', ['trail' => [['Sign in', 'account/login/'], ['Verification', null]]]);
?>
<div class="auth-layout auth-layout-narrow">
  <section class="section-panel auth-panel flow-lg" aria-labelledby="page-title">
    <div class="flow">
      <p class="eyebrow">Two-factor authentication</p>
      <h1 id="page-title">Enter your code</h1>
      <p>Open your authenticator app and enter the 6-digit code for UV's Compendium. You can also use one of your recovery codes.</p>
    </div>
    <form class="form-stack" method="post" action="<?= $view->url('account/login/verify/') ?>">
      <?= $view->csrf() ?>
      <?php if ($next !== null): ?><input type="hidden" name="next" value="/<?= h($next) ?>"><?php endif; ?>
      <div class="form-field">
        <label for="code">Authentication or recovery code</label>
        <input id="code" name="code" type="text" required maxlength="40" autocomplete="one-time-code"
          autocapitalize="none" spellcheck="false" inputmode="text"<?= $view->invalid('code') ?>>
        <?= $view->fieldError('code') ?>
      </div>
      <div class="form-actions">
        <button class="button button-primary" type="submit">Verify</button>
        <a class="text-link" href="<?= $view->url('account/login/') ?>">Start over</a>
      </div>
    </form>
  </section>
</div>
