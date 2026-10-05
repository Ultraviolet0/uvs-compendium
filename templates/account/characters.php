<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $characters
 * @var ?array<string, mixed> $editing
 * @var array<string, array<string, string>> $options
 * @var int $max
 */
$view->partial('breadcrumbs', ['trail' => [['Your account', 'account/'], ['Characters', null]]]);
$count = count($characters);
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <div class="flow">
    <p class="eyebrow">Your account</p>
    <h1 id="page-title"><?= $editing === null ? 'Characters' : 'Edit ' . h((string) $editing['name']) ?></h1>
    <p>List the Diablo and Hellfire heroes you play. They appear on your public profile.</p>
  </div>
  <?php $view->partial('account-nav', ['active' => 'characters']); ?>
</section>

<?php if ($editing !== null): ?>
  <section class="section-panel flow" aria-labelledby="edit-title">
    <h2 id="edit-title">Edit character</h2>
    <?php $view->partial('error-summary', ['fields' => ['name', 'game', 'class', 'level', 'play_mode', 'platform', 'notes']]); ?>
    <form class="form-stack" method="post" action="<?= $view->url('account/characters/' . (int) $editing['id'] . '/') ?>">
      <?= $view->csrf() ?>
      <?php $view->partial('character-fields', ['options' => $options]); ?>
      <div class="form-actions">
        <button class="button button-primary" type="submit">Save character</button>
        <a class="text-link" href="<?= $view->url('account/characters/') ?>">Cancel</a>
      </div>
    </form>
  </section>
<?php else: ?>
  <section class="section-panel flow" aria-labelledby="list-title">
    <h2 id="list-title">Your heroes <span class="count-pill"><?= $count ?></span></h2>
    <?php if ($characters === []): ?>
      <div class="empty-state">
        <p class="empty-state-title">No characters yet</p>
        <p>Add your first hero below.</p>
      </div>
    <?php else: ?>
      <ol class="character-list">
        <?php foreach ($characters as $index => $character): ?>
          <li class="character-card" id="character-<?= (int) $character['id'] ?>">
            <?php $view->partial('character-card', ['character' => $character, 'options' => $options]); ?>
            <div class="character-actions">
              <a class="button button-secondary button-small" href="<?= $view->url('account/characters/' . (int) $character['id'] . '/edit/') ?>">Edit<span class="sr-only"> <?= h((string) $character['name']) ?></span></a>
              <?php if ($index > 0): ?>
                <form method="post" action="<?= $view->url('account/characters/' . (int) $character['id'] . '/move/') ?>">
                  <?= $view->csrf() ?><input type="hidden" name="direction" value="up">
                  <button class="button button-quiet button-small" type="submit">Move up<span class="sr-only"> <?= h((string) $character['name']) ?></span></button>
                </form>
              <?php endif; ?>
              <?php if ($index < $count - 1): ?>
                <form method="post" action="<?= $view->url('account/characters/' . (int) $character['id'] . '/move/') ?>">
                  <?= $view->csrf() ?><input type="hidden" name="direction" value="down">
                  <button class="button button-quiet button-small" type="submit">Move down<span class="sr-only"> <?= h((string) $character['name']) ?></span></button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= $view->url('account/characters/' . (int) $character['id'] . '/delete/') ?>" data-confirm="Remove <?= h((string) $character['name']) ?>?">
                <?= $view->csrf() ?>
                <button class="button button-danger button-small" type="submit">Remove<span class="sr-only"> <?= h((string) $character['name']) ?></span></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>

  <?php if ($count < $max): ?>
    <section class="section-panel flow" aria-labelledby="add-title">
      <h2 id="add-title">Add a character</h2>
      <?php $view->partial('error-summary', ['fields' => ['name', 'game', 'class', 'level', 'play_mode', 'platform', 'notes']]); ?>
      <form class="form-stack" method="post" action="<?= $view->url('account/characters/') ?>">
        <?= $view->csrf() ?>
        <?php $view->partial('character-fields', ['options' => $options]); ?>
        <button class="button button-primary" type="submit">Add character</button>
      </form>
    </section>
  <?php endif; ?>
<?php endif; ?>
