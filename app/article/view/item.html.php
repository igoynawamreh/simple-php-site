<?php require __DIR__ . '/../../before.html.php'; ?>

<h1><?= e($page['title']) ?></h1>

<p>
  <a href="<?= url($page['index_url']) ?>"><?= e($page['index_title']) ?></a>
  <span><?= formatDate($page['date'], 'd M Y') ?></span>
</p>

<p>
  Category:
  <?php if (!empty($page['category'])): ?>
    <a class="badge text-bg-secondary" href="<?= url($page['index_url']) . '?category=' . urlencode($page['category']) ?>">
      <?= e($page['category']) ?>
    </a>
  <?php endif ?>
</p>

<p>
  Tags:
  <?php if (!empty($page['tags'])): ?>
    <span class="d-inline-flex gap-1">
      <?php foreach ($page['tags'] as $tag): ?>
        <a class="badge text-bg-secondary" href="<?= url($page['index_url']) . '?tag=' . urlencode($tag) ?>">
          <?= e($tag) ?>
        </a>
      <?php endforeach ?>
    </span>
  <?php endif ?>
</p>

<div>
  <?= $page['content'] ?>
</div>

<?php require __DIR__ . '/../../after.html.php'; ?>
