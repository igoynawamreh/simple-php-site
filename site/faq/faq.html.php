<?php require __DIR__ . '/../before.html.php'; ?>

<h1><?= e($page['title']); ?></h1>

<p><?= format_date($page['date'], 'd M Y') ?></p>

<hr>

<div>
  <?= $page['content'] ?>
</div>

<?php require __DIR__ . '/../after.html.php'; ?>
