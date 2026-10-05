<?php
/**
 * @var \Uvs\Security\Turnstile\Turnstile $turnstile
 * @var string $action
 * @var \Uvs\Http\View $view
 */
?>
<?php if ($turnstile->status() === 'enabled'): ?>
  <div class="form-field">
    <p class="field-label" id="turnstile-label">Anti-bot check</p>
    <div class="cf-turnstile" data-sitekey="<?= h((string) $turnstile->siteKey()) ?>" data-action="<?= h($action) ?>" data-theme="dark" data-size="flexible" aria-describedby="turnstile-label"></div>
    <noscript><p class="field-help">The anti-bot check needs JavaScript from Cloudflare.</p></noscript>
    <?= $view->fieldError('turnstile') ?>
  </div>
<?php elseif ($turnstile->status() === 'test'): ?>
  <div class="form-field notice notice-info">
    <p><strong>Test mode:</strong> Cloudflare Turnstile is replaced by an offline check in this development environment.</p>
    <input type="hidden" name="cf-turnstile-response" value="<?= h(\Uvs\Security\Turnstile\TestVerifier::PASSING_TOKEN) ?>">
    <?= $view->fieldError('turnstile') ?>
  </div>
<?php endif; ?>
