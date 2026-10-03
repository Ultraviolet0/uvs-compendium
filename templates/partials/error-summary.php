<?php
/** @var \Uvs\Http\View $view */
$summaryErrors = $view->errors();
?>
<?php if ($summaryErrors !== []): ?>
  <div class="error-summary" role="alert" aria-labelledby="error-summary-title" tabindex="-1" data-error-summary>
    <p id="error-summary-title" class="error-summary-title">Please fix the following:</p>
    <ul>
      <?php foreach ($summaryErrors as $field => $message): ?>
        <li><?php if (in_array($field, $fields ?? [], true)): ?><a href="#<?= h((string) $field) ?>"><?= h($message) ?></a><?php else: ?><?= h($message) ?><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
