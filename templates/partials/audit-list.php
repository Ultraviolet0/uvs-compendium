<?php
/**
 * @var list<array<string, mixed>> $events
 */
use Uvs\Admin\AuditLog;
use Uvs\Support\Text;
?>
<?php if ($events === []): ?>
  <p class="text-muted">No recorded actions.</p>
<?php else: ?>
  <ol class="history-list">
    <?php foreach ($events as $event): $meta = $event['metadata'] !== null ? json_decode((string) $event['metadata'], true) : null; ?>
      <li>
        <strong><?= h(AuditLog::LABELS[$event['action']] ?? (string) $event['action']) ?></strong>
        <span class="text-muted">by <?= h((string) $event['actor_label']) ?> · <?= h(Text::dateTime((string) $event['created_at'])) ?></span>
        <?php if (is_array($meta) && $meta !== []): ?>
          <dl class="history-meta">
            <?php foreach ($meta as $key => $value): ?>
              <div><dt><?= h((string) $key) ?></dt><dd><?= h(is_scalar($value) ? (string) $value : (string) json_encode($value)) ?></dd></div>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
