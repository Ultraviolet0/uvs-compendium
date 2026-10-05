<?php
/**
 * @var array<string, mixed> $character
 * @var array<string, array<string, string>> $options
 */
$facts = array_filter([
  $options['games'][$character['game']] ?? null,
  $character['level'] !== null ? 'Level ' . (int) $character['level'] : null,
  $character['play_mode'] !== null ? ($options['modes'][$character['play_mode']] ?? null) : null,
  $character['platform'] !== null ? ($options['platforms'][$character['platform']] ?? null) : null,
]);
?>
<div class="character-card-body">
  <p class="character-class class-<?= h((string) $character['class']) ?>"><?= h($options['classes'][$character['class']] ?? '') ?></p>
  <p class="character-name"><?= h((string) $character['name']) ?></p>
  <p class="character-facts"><?= h(implode(' · ', $facts)) ?></p>
  <?php if (!empty($character['notes'])): ?>
    <p class="character-notes"><?= h((string) $character['notes']) ?></p>
  <?php endif; ?>
</div>
