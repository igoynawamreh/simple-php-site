<?php require __DIR__ . '/../before.html.php'; ?>

<h1><?= e($page['title']); ?></h1>

<p><?= format_date($page['date'], 'd M Y') ?></p>

<hr>

<ul>
  <li>
    Segment 1: <a href="<?= url('/about/abcd') ?>">/about/abcd</a>
  </li>
  <li>
    Segment 2: <a href="<?= url('/about/abcd/1234') ?>">/about/abcd/1234</a>
  </li>
</ul>

<div>
  <?= $page['content'] ?>
</div>

<?php require __DIR__ . '/../after.html.php'; ?>
