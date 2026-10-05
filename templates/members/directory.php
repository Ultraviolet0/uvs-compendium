<?php
/**
 * @var \Uvs\Http\View $view
 * @var list<array<string, mixed>> $members
 * @var string $query
 * @var int $page
 * @var int $pages
 * @var int $total
 */
$view->partial('breadcrumbs', ['trail' => [['Members', null]]]);
?>
<section class="section-panel flow-lg" aria-labelledby="page-title">
  <div class="flow">
    <p class="eyebrow">Community</p>
    <h1 id="page-title">Members</h1>
    <p class="hero-copy">The players who write and review guides for UV's Compendium.</p>
  </div>
  <form class="search-form" method="get" action="<?= $view->url('members/') ?>" role="search">
    <label for="member-search">Search members</label>
    <div class="search-row">
      <input id="member-search" name="q" type="search" maxlength="24" value="<?= h($query) ?>" autocapitalize="none" spellcheck="false">
      <button class="button button-secondary" type="submit">Search</button>
    </div>
  </form>
</section>

<section class="flow" aria-labelledby="results-title">
  <h2 id="results-title" class="sr-only">Member list</h2>
  <?php if ($members === []): ?>
    <div class="empty-state section-panel">
      <p class="empty-state-title"><?= $query === '' ? 'No members yet' : 'No members match “' . h($query) . '”' ?></p>
      <p><a class="text-link" href="<?= $view->url('account/signup/') ?>">Create an account</a> to be one of the first.</p>
    </div>
  <?php else: ?>
    <p class="text-muted" aria-live="polite"><?= number_format($total) ?> member<?= $total === 1 ? '' : 's' ?></p>
    <ul class="member-grid">
      <?php foreach ($members as $member): ?>
        <li class="member-card">
          <?php uvs_avatar($member, 'md'); ?>
          <div class="member-card-text">
            <a class="member-name" href="<?= $view->url('members/' . rawurlencode((string) $member['username']) . '/') ?>"><?= h((string) $member['username']) ?></a>
            <p class="member-meta">
              <?php if ($member['role'] === 'admin'): ?><span class="role-tag">Admin</span><?php endif; ?>
              <?= (int) $member['guide_count'] ?> guide<?= (int) $member['guide_count'] === 1 ? '' : 's' ?> · Joined <?= $view->date((string) $member['created_at'], 'M Y') ?>
            </p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => 'members/', 'query' => ['q' => $query]]); ?>
  <?php endif; ?>
</section>
