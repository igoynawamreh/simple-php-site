<?php require __DIR__ . '/before.html.php'; ?>

<?php
$blog_md    = new Markdown('/blog');
$blog_pages = $blog_md->getPages(
  route: '/blog',
  count: 5,
);
?>

<h1><?= e($page['title']) ?></h1>

<hr>

<?php if (!empty($blog_pages)): ?>
  <h2><?= e($blog_pages['title']) ?></h2>
  <ul class="d-flex flex-column gap-2">
    <?php foreach ($blog_pages['pages'] as $blog): ?>
      <li>
        <div class="d-flex flex-column">
          <a href="<?= url($blog['route']) ?>">
            <?= e($blog['title']) ?>
          </a>
          <span><?= format_date($blog['date'], 'd M Y') ?></span>
        </div>
      </li>
    <?php endforeach ?>
    <li>
      <a href="<?= url($blog_pages['route']) ?>">View all</a>
    </li>
  </ul>
<?php endif ?>

<?php require __DIR__ . '/after.html.php'; ?>
