<?php
/**
 * @var \Uvs\Http\View $view
 * @var list<array<string, mixed>> $rows
 * @var int $total
 * @var string $filter
 * @var string $query
 * @var int $page
 * @var int $pages
 * @var array<string, int> $counts
 * @var array<string, string> $filters
 */
use Uvs\Guides\GuideStatus;

$view->partial('admin-header', ['section' => 'guides', 'title' => 'Guides', 'trail' => [['Guides', null]]]);
?>
<nav class="filter-tabs" aria-label="Guide states">
  <ul>
    <?php foreach ($filters as $key => $label): ?>
      <li><a class="filter-tab<?= $key === $filter ? ' is-active' : '' ?>" href="<?= $view->url('admin/guides/') ?>?filter=<?= h($key) ?>"<?= $key === $filter ? ' aria-current="page"' : '' ?>><?= h($label) ?> <span class="count-pill"><?= (int) ($counts[$key] ?? 0) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</nav>

<section class="section-panel flow" aria-labelledby="results-title">
  <div class="section-heading-row">
    <h2 id="results-title"><?= h($filters[$filter]) ?> <span class="count-pill"><?= $total ?></span></h2>
    <form class="search-form search-form-inline" method="get" action="<?= $view->url('admin/guides/') ?>" role="search">
      <input type="hidden" name="filter" value="<?= h($filter) ?>">
      <label for="guide-search" class="sr-only">Search title, URL, or author</label>
      <div class="search-row">
        <input id="guide-search" name="q" type="search" maxlength="100" value="<?= h($query) ?>" placeholder="Title, URL, or author">
        <button class="button button-secondary button-small" type="submit">Search</button>
      </div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <div class="empty-state"><p class="empty-state-title">Nothing here</p><p><?= $filter === 'queue' ? 'The review queue is empty.' : 'No guides are in this state.' ?></p></div>
  <?php else: ?>
    <div class="table-wrap" tabindex="0" role="region" aria-labelledby="results-title">
      <table class="data-table">
        <thead><tr><th scope="col">Guide</th><th scope="col">Author</th><th scope="col">State</th><th scope="col"><?= $filter === 'queue' ? 'Submitted' : 'Last change' ?></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): $status = GuideStatus::describe($row); ?>
            <tr>
              <th scope="row" data-label="Guide"><a href="<?= $view->url('admin/guides/' . (int) $row['id'] . '/') ?>"><?= h((string) $row['title']) ?></a><span class="cell-detail">/guides/<?= h((string) $row['slug']) ?>/</span></th>
              <td data-label="Author"><?= $row['author_username'] !== null ? '<a href="' . $view->url('admin/users/' . (int) $row['author_id'] . '/') . '">' . h((string) $row['author_username']) . '</a>' : 'Former member' ?></td>
              <td data-label="State"><?php $view->partial('status-badge', ['label' => $status['label'], 'tone' => $status['tone']]); ?></td>
              <td data-label="<?= $filter === 'queue' ? 'Submitted' : 'Last change' ?>"><?= $view->time((string) ($filter === 'queue' ? $row['submitted_at'] : $row['updated_at']), 'M j, Y') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => 'admin/guides/', 'query' => ['filter' => $filter, 'q' => $query]]); ?>
  <?php endif; ?>
</section>
