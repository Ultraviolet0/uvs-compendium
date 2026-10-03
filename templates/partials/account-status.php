<?php
/**
 * @var array<string, mixed> $user
 */
?>
<?php if ($user['status'] === 'pending'): ?>
  <div class="notice notice-info account-status-notice">
    <p><strong>Your account is awaiting approval.</strong> An administrator reviews new accounts before they can write and submit guides. Meanwhile you can set up your profile, add characters, and secure your account.</p>
  </div>
<?php endif; ?>
