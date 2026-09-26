<?php require __DIR__ . '/before.html.php'; ?>

<?php
$article_list = generate_list(
  path: '/article',
  count: 5,
);
?>

<h1><?= e($page['title']) ?></h1>

<hr>

<?php if (!empty($article_list)): ?>
  <h2><?= e($article_list['title']) ?></h2>
  <ul>
    <?php foreach ($article_list['list'] as $article): ?>
      <li>
        <div class="d-flex gap-3">
          <a href="<?= url($article['url']) ?>">
            <?= e($article['title']) ?>
          </a>
          <span><?= formatDate($article['date'], 'd M Y') ?></span>
        </div>
      </li>
    <?php endforeach ?>
    <li>
      <a href="<?= url($article_list['url']) ?>">View all</a>
    </li>
  </ul>
<?php endif ?>

<?php require __DIR__ . '/after.html.php'; ?>
