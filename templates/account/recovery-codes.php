<?php
/**
 * @var \Uvs\Http\View $view
 * @var list<string> $codes
 * @var bool $justEnabled
 */
$view->partial('breadcrumbs', ['trail' => [['Your account', 'account/'], ['Security', 'account/security/'], ['Recovery codes', null]]]);
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <div class="flow">
    <p class="eyebrow">Account security</p>
    <h1 id="page-title">Save your recovery codes</h1>
    <?php if ($justEnabled): ?>
      <p class="notice notice-success">Two-factor authentication is on.</p>
    <?php endif; ?>
    <p>Each code works once if you lose access to your authenticator app. Store them somewhere safe, such as a password manager. <strong>They will not be shown again.</strong></p>
  </div>
  <ul class="recovery-codes" id="recovery-codes">
    <?php foreach ($codes as $code): ?>
      <li><code><?= h($code) ?></code></li>
    <?php endforeach; ?>
  </ul>
  <div class="form-actions">
    <button class="button button-secondary" type="button" data-copy-target="recovery-codes" hidden>Copy codes</button>
    <a class="button button-primary" href="<?= $view->url('account/security/') ?>">I have saved these codes</a>
  </div>
</section>
