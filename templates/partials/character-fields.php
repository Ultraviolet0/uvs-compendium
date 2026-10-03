<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, array<string, string>> $options
 */
$select = static function (string $name, string $label, array $choices, bool $optional) use ($view): void {
  echo '<div class="form-field"><label for="' . h($name) . '">' . h($label) . ($optional ? ' <span class="optional">(optional)</span>' : '') . '</label>';
  echo '<select id="' . h($name) . '" name="' . h($name) . '"' . ($optional ? '' : ' required') . $view->invalid($name) . '>';
  echo '<option value="">' . ($optional ? 'Not specified' : 'Choose…') . '</option>';
  foreach ($choices as $value => $text) {
    echo '<option value="' . h((string) $value) . '"' . ($view->old($name) === (string) $value ? ' selected' : '') . '>' . h($text) . '</option>';
  }
  echo '</select>' . $view->fieldError($name) . '</div>';
};
?>
<div class="form-grid">
  <div class="form-field">
    <label for="name">Character name</label>
    <input id="name" name="name" type="text" required maxlength="15" value="<?= h($view->old('name')) ?>"<?= $view->invalid('name') ?>>
    <?= $view->fieldError('name') ?>
  </div>
  <?php $select('game', 'Game', $options['games'], false); ?>
  <?php $select('class', 'Class', $options['classes'], false); ?>
  <div class="form-field">
    <label for="level">Level <span class="optional">(optional)</span></label>
    <input id="level" name="level" type="number" min="1" max="50" inputmode="numeric" value="<?= h($view->old('level')) ?>"<?= $view->invalid('level') ?>>
    <?= $view->fieldError('level') ?>
  </div>
  <?php $select('play_mode', 'Play mode', $options['modes'], true); ?>
  <?php $select('platform', 'Version', $options['platforms'], true); ?>
</div>
<div class="form-field">
  <label for="notes">Notes <span class="optional">(optional)</span></label>
  <input id="notes" name="notes" type="text" maxlength="280" value="<?= h($view->old('notes')) ?>"<?= $view->invalid('notes', 'notes-help') ?>>
  <p class="field-help" id="notes-help">For example a build, a goal, or the server you play on. Monk, Bard, and Barbarian are Hellfire-only.</p>
  <?= $view->fieldError('notes') ?>
</div>
