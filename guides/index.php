<?php
$page_title = "Guides | UV's Compendium";
$page_description = "Strategy guides and mechanics references for Diablo I, Hellfire, and DevilutionX.";
$base_path = '../';
$current_page = 'guides';
$page_styles = ['guides/css/styles.css'];

require_once __DIR__ . '/../includes/public_header.php';
$community_guides = uvs_published_community_guides();
?>

<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <li aria-current="page">Guides</li>
  </ol>
</nav>

<section class="section-panel flow-lg" aria-labelledby="page-title">
  <p class="eyebrow">Documentation</p>
  <h1 id="page-title">Strategy Guides</h1>
  <p class="hero-copy">
    Browse practical Diablo I, Hellfire, and DevilutionX guides from UV's Compendium and the wider community.
  </p>
</section>

<section class="content-grid guide-catalog" aria-label="Guide catalog">
  <article class="card flow">
    <p class="card-label">Strategy Guide</p>
    <h2>Fast Character Development</h2>
    <p>A practical Diablo I and Hellfire route for leveling, gearing, spell development, shrine hunting, and efficient farming.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="<?= site_url('guides/fast-character-development/') ?>">Read the Guide</a></p>
  </article>

  <article class="card flow">
    <p class="card-label">Strategy Guide</p>
    <h2>UV's Shopping &amp; Affixes</h2>
    <p>A detailed guide to vendor mechanics, shopper levels, item-pool manipulation, price anchors, and targeted equipment shopping.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="<?= site_url('guides/shopping/') ?>">Read the Guide</a></p>
  </article>

  <article class="card flow">
    <p class="card-label">Video Guide</p>
    <h2>Max's Hellfire Shopping Video</h2>
    <p>Max's practical Hellfire shopping demonstration, using a King's Bastard Sword of Speed or Haste as the target.</p>
    <p class="guide-card-action"><a class="button button-secondary" href="<?= site_url('guides/max-shopping-video/') ?>">Watch the Video</a></p>
  </article>
</section>

<?php if ($community_guides !== null): ?>
  <section class="section-panel flow-lg community-guides" id="community-guides" aria-labelledby="community-guides-title">
    <div class="section-heading-row">
      <div class="flow">
        <p class="eyebrow">From the community</p>
        <h2 id="community-guides-title">Community Guides</h2>
        <p>Guides written by members and reviewed before publication.</p>
      </div>
      <a class="button button-secondary" href="<?= site_url('account/guides/new/') ?>">Write a guide</a>
    </div>

    <?php if ($community_guides === []): ?>
      <div class="empty-state">
        <p class="empty-state-title">No community guides yet</p>
        <p>Approved members can submit guides for review. Yours could be the first.</p>
      </div>
    <?php else: ?>
      <ul class="community-guide-list">
        <?php foreach ($community_guides as $community_guide): ?>
          <li class="card community-guide-card flow">
            <p class="card-label">Community Guide</p>
            <h3><a href="<?= site_url('guides/' . $community_guide['slug'] . '/') ?>"><?= h((string) $community_guide['title']) ?></a></h3>
            <?php if ((string) $community_guide['summary'] !== ''): ?>
              <p><?= h((string) $community_guide['summary']) ?></p>
            <?php endif; ?>
            <p class="community-guide-byline">
              <?php if ($community_guide['author_username'] !== null): ?>
                <?php uvs_avatar(['username' => $community_guide['author_username'], 'avatar_public_id' => $community_guide['author_avatar_public_id'], 'avatar_extension' => $community_guide['author_avatar_extension']], 'xs'); ?>
                <?php if ($community_guide['author_status'] === 'active'): ?>
                  <a href="<?= site_url('members/' . rawurlencode((string) $community_guide['author_username']) . '/') ?>"><?= h((string) $community_guide['author_username']) ?></a>
                <?php else: ?>
                  <span><?= h((string) $community_guide['author_username']) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span>A former member</span>
              <?php endif; ?>
              <span aria-hidden="true">·</span>
              <time datetime="<?= h(substr((string) $community_guide['first_published_at'], 0, 10)) ?>"><?= h(date('M j, Y', strtotime((string) $community_guide['first_published_at'] . ' UTC'))) ?></time>
            </p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/public_footer.php'; ?>
