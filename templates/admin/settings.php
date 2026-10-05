<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, array<string, mixed>> $definitions
 * @var array<string, bool|int> $values
 * @var array<string, string> $system
 */
$view->partial('admin-header', ['section' => 'settings', 'title' => 'Settings', 'trail' => [['Settings', null]]]);
?>
<?php $view->partial('error-summary', ['fields' => array_keys($definitions)]); ?>
<form class="section-panel form-stack" method="post" action="<?= $view->url('admin/settings/') ?>" data-confirm="Save these settings? Changes take effect immediately.">
  <?= $view->csrf() ?>
  <fieldset class="settings-group">
    <legend>Accounts and submissions</legend>
    <?php foreach ($definitions as $name => $definition): ?>
      <?php if ($definition['type'] !== 'bool') continue; ?>
      <div class="form-check setting-row<?= !empty($definition['scaffold']) ? ' is-disabled' : '' ?>">
        <input id="<?= h($name) ?>" name="<?= h($name) ?>" type="checkbox" value="1"<?= $values[$name] ? ' checked' : '' ?><?= !empty($definition['scaffold']) ? ' disabled' : '' ?> aria-describedby="<?= h($name) ?>-help">
        <label for="<?= h($name) ?>"><?= h($definition['label']) ?></label>
        <p class="field-help" id="<?= h($name) ?>-help"><?= h($definition['help']) ?></p>
        <?= $view->fieldError($name) ?>
      </div>
    <?php endforeach; ?>
  </fieldset>
  <fieldset class="settings-group">
    <legend>Image uploads</legend>
    <div class="form-grid">
      <?php foreach ($definitions as $name => $definition): ?>
        <?php if ($definition['type'] !== 'int') continue; ?>
        <div class="form-field">
          <label for="<?= h($name) ?>"><?= h($definition['label']) ?></label>
          <input id="<?= h($name) ?>" name="<?= h($name) ?>" type="number" min="<?= (int) $definition['min'] ?>" max="<?= (int) $definition['max'] ?>" value="<?= (int) $values[$name] ?>" required aria-describedby="<?= h($name) ?>-help"<?= $view->error($name) !== null ? ' aria-invalid="true"' : '' ?>>
          <p class="field-help" id="<?= h($name) ?>-help"><?= h($definition['help']) ?></p>
          <?= $view->fieldError($name) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <button class="button button-primary" type="submit">Save settings</button>
</form>

<section class="section-panel flow" aria-labelledby="system-title">
  <h2 id="system-title">Private configuration</h2>
  <p class="field-help">These come from the private configuration file outside the web root. Secret values are never displayed. See <code>docs/configuration.md</code>.</p>
  <dl class="detail-list">
    <?php foreach ($system as $label => $value): ?>
      <div><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd></div>
    <?php endforeach; ?>
  </dl>
</section>
