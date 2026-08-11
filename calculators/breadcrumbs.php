<?php

declare(strict_types=1);

$calculator_breadcrumb_title = isset($calculator_breadcrumb_title) && is_string($calculator_breadcrumb_title)
  ? trim($calculator_breadcrumb_title)
  : 'Calculator';
$is_calculator_landing_page = $calculator_breadcrumb_title === 'Calculators';
?>

<nav class="calculator-breadcrumbs" aria-label="Breadcrumb">
  <ol class="calculator-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <?php if (!$is_calculator_landing_page): ?>
      <li><a href="<?= site_url('calculators/') ?>">Calculators</a></li>
    <?php endif; ?>
    <li aria-current="page"><?= h($calculator_breadcrumb_title) ?></li>
  </ol>
</nav>

<?php
unset($calculator_breadcrumb_title, $is_calculator_landing_page);
?>
