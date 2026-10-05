<?php
/**
 * @var string $label
 * @var string $tone success|info|warning|danger|muted
 */
?>
<span class="status-badge status-<?= h(in_array($tone, ['success', 'info', 'warning', 'danger', 'muted'], true) ? $tone : 'muted') ?>"><?= h($label) ?></span>
