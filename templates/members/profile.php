<?php
/**
 * @var \Uvs\Http\View $view
 * @var array<string, mixed> $member public columns only
 * @var list<array<string, mixed>> $characters
 * @var list<array<string, mixed>> $guides
 * @var array<string, array<string, string>> $options
 * @var array<string, string> $games
 * @var bool $isSelf
 */
$view->partial('breadcrumbs', ['trail' => [['Members', 'members/'], [(string) $member['username'], null]]]);
?>
<section class="section-panel profile-hero" aria-labelledby="page-title">
  <div class="identity-row">
    <?php uvs_avatar($member, 'xl', false); ?>
    <div class="flow">
      <p class="eyebrow"><?= $member['role'] === 'admin' ? 'Compendium administrator' : 'Member' ?></p>
      <h1 id="page-title"><?= h((string) $member['username']) ?></h1>
      <dl class="profile-facts">
        <div><dt>Joined</dt><dd><?= $view->time((string) $member['created_at'], 'F Y') ?></dd></div>
        <div><dt>Guides</dt><dd><?= count($guides) ?></dd></div>
        <?php if (!empty($member['preferred_game'])): ?>
          <div><dt>Plays</dt><dd><?= h($games[$member['preferred_game']] ?? '') ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($member['discord_handle'])): ?>
          <div><dt>Discord</dt><dd><?= h((string) $member['discord_handle']) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($member['website_url'])): ?>
          <div><dt>Website</dt><dd><a class="text-link" href="<?= h((string) $member['website_url']) ?>" rel="nofollow ugc noopener noreferrer"><?= h((string) parse_url((string) $member['website_url'], PHP_URL_HOST)) ?></a></dd></div>
        <?php endif; ?>
      </dl>
    </div>
  </div>
  <?php if (!empty($member['bio'])): ?>
    <div class="profile-bio"><?= nl2br(h((string) $member['bio'])) ?></div>
  <?php endif; ?>
  <?php if ($isSelf): ?>
    <p><a class="button button-secondary button-small" href="<?= $view->url('account/profile/') ?>">Edit your profile</a></p>
  <?php endif; ?>
</section>

<section class="section-panel flow" aria-labelledby="guides-title">
  <h2 id="guides-title">Published guides</h2>
  <?php if ($guides === []): ?>
    <p class="text-muted"><?= h((string) $member['username']) ?> has not published a guide yet.</p>
  <?php else: ?>
    <ul class="guide-link-list">
      <?php foreach ($guides as $guide): ?>
        <li>
          <a class="guide-row-title" href="<?= $view->url('guides/' . $guide['slug'] . '/') ?>"><?= h((string) $guide['title']) ?></a>
          <p class="text-muted"><?= h((string) $guide['summary']) ?></p>
          <p class="guide-row-meta">Published <?= $view->time((string) $guide['first_published_at']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="section-panel flow" aria-labelledby="characters-title">
  <h2 id="characters-title">Characters</h2>
  <?php if ($characters === []): ?>
    <p class="text-muted">No characters listed.</p>
  <?php else: ?>
    <ul class="character-list character-list-public">
      <?php foreach ($characters as $character): ?>
        <li class="character-card"><?php $view->partial('character-card', ['character' => $character, 'options' => $options]); ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
