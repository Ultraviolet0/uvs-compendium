<?php

declare(strict_types=1);

/*
 * Account section of the shared sidebar. Expects $viewer_context from
 * public_header.php. Rendered for every page; never queries the database itself.
 */

$account_user = $viewer_context['user'] ?? null;
$account_counts = $viewer_context['admin_counts'] ?? [];
$account_queue = (int) ($account_counts['users'] ?? 0) + (int) ($account_counts['guides'] ?? 0);
?>
<section class="nav-section nav-account" aria-labelledby="nav-account-heading">
  <p id="nav-account-heading" class="nav-heading">Community</p>

  <?php if ($account_user === null): ?>
    <ul class="nav-list">
      <?php if ($viewer_context['available'] ?? false): ?>
        <li class="nav-item"><a class="nav-link nav-page-link" href="<?= site_url('account/login/') ?>">Sign in</a></li>
        <li class="nav-item"><a class="nav-link nav-page-link" href="<?= site_url('account/signup/') ?>">Create an account</a></li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link nav-page-link" href="<?= site_url('account/login/') ?>">Member sign in</a></li>
      <?php endif; ?>
    </ul>
  <?php else: ?>
    <div class="nav-identity">
      <?php uvs_avatar($account_user, 'sm'); ?>
      <div class="nav-identity-text">
        <span class="nav-identity-name"><?= h((string) $account_user['username']) ?></span>
        <span class="nav-identity-status"><?= h(uvs_account_status_label($account_user)) ?></span>
      </div>
    </div>
    <ul class="nav-list">
      <li class="nav-item"><a class="nav-link nav-page-link" href="<?= site_url('account/') ?>">Dashboard</a></li>
      <?php if (($account_user['status'] ?? '') === 'active'): ?>
        <li class="nav-item"><a class="nav-link nav-page-link" href="<?= site_url('account/guides/') ?>">My guides</a></li>
        <li class="nav-item"><a class="nav-link nav-page-link" href="<?= site_url('members/' . rawurlencode((string) $account_user['username']) . '/') ?>">Public profile</a></li>
      <?php endif; ?>
      <?php if ($account_counts !== []): ?>
        <li class="nav-item">
          <a class="nav-link nav-page-link nav-link-with-badge" href="<?= site_url('admin/') ?>">
            <span>Admin</span>
            <?php if ($account_queue > 0): ?>
              <span class="nav-badge"><?= $account_queue ?><span class="sr-only"> items awaiting review</span></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endif; ?>
      <li class="nav-item">
        <form class="nav-signout" method="post" action="<?= site_url('account/logout/') ?>">
          <input type="hidden" name="_csrf" value="<?= h((string) $viewer_context['csrf']) ?>">
          <button class="nav-link nav-page-link nav-signout-button" type="submit">Sign out</button>
        </form>
      </li>
    </ul>
  <?php endif; ?>

</section>
