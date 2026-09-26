<?php require __DIR__ . '/../before.html.php'; ?>

<h1><?= e($page['title']); ?></h1>

<hr>

<p><?= $page['foo'] ?></p>

<div>
  <?= $page['content'] ?>
</div>

<?php require __DIR__ . '/../after.html.php'; ?>
