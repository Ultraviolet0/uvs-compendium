<?php
/**
 * @var \Uvs\Http\View $view
 * @var int $status
 * @var string $title
 * @var ?string $detail
 */
$messages = [
  403 => 'You do not have permission to view this page.',
  404 => 'The page you were looking for is not here. It may have moved, or it may not be public.',
  429 => 'Too many requests. Please wait a little and try again.',
  500 => 'An unexpected error occurred. It has been logged; please try again later.',
];
?>
<section class="section-panel flow-lg error-page" aria-labelledby="page-title">
  <p class="eyebrow">Error <?= (int) $status ?></p>
  <h1 id="page-title"><?= h($title) ?></h1>
  <p class="hero-copy"><?= h($detail ?? ($messages[$status] ?? 'The request could not be completed.')) ?></p>
  <div class="button-row">
    <a class="button button-primary" href="<?= $view->url() ?>">Return home</a>
    <a class="button button-secondary" href="<?= $view->url('guides/') ?>">Browse guides</a>
    <?php if ($status === 403 && $view->user() === null): ?>
      <a class="button button-secondary" href="<?= $view->url('account/login/') ?>">Sign in</a>
    <?php endif; ?>
  </div>
</section>
