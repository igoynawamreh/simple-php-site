<?php require __DIR__ . '/../../before.html.php'; ?>

<?php
$category_list = generate_category_list('/article');
$tag_list = generate_tag_list('/article');
?>

<h1><?= e($page['title']) ?></h1>

<hr>

<?php if (!empty($category_list) || !empty($tag_list)): ?>
  <div class="btn-toolbar mb-3 gap-3">
    <?php if (!empty($category_list)): ?>
      <div class="dropdown">
        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
          Category<?= $category_list['selected'] ? ': ' . $category_list['selected'] : '' ?>
        </button>
        <ul class="dropdown-menu">
          <?php foreach ($category_list['list'] as $category): ?>
            <li>
              <a class="dropdown-item<?= $category['active'] ? ' active' : '' ?>" href="<?= url(merge_query_url($category['url'])) ?>">
                <?= e($category['title']) ?>
              </a>
            </li>
          <?php endforeach ?>
        </ul>
      </div>
    <?php endif ?>

    <?php if (!empty($tag_list)): ?>
      <div class="dropdown">
        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
          Tags<?= $tag_list['selected'] ? ': ' . $tag_list['selected'] : '' ?>
        </button>
        <ul class="dropdown-menu">
          <?php foreach ($tag_list['list'] as $tag): ?>
            <li>
              <a class="dropdown-item<?= $tag['active'] ? ' active' : '' ?>" href="<?= url(merge_query_url($tag['url'])) ?>">
                <?= e($tag['title']) ?>
              </a>
            </li>
          <?php endforeach ?>
        </ul>
      </div>
    <?php endif ?>

    <?php if ($category_list['selected'] || $tag_list['selected']): ?>
      <a class="btn btn-light btn-sm lh-1 d-inline-flex align-items-center justify-content-center" href="<?= url($page['index_url']) ?>">
        <i class="bi bi-x"></i>
      </a>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if (!empty($pages['list'])): ?>
  <ul>
    <?php foreach ($pages['list'] as $page): ?>
      <li>
        <div class="d-flex gap-3">
          <a href="<?= url($page['url']) ?>">
            <?= e($page['title']) ?>
          </a>
          <span><?= formatDate($page['date'], 'd M Y') ?></span>
        </div>
      </li>
    <?php endforeach ?>
  </ul>

  <?php if (!empty($pagination)): ?>
    <nav>
      <ul class="pagination">
        <?php if ($pagination['prev']): ?>
          <li class="page-item">
            <a class="page-link" href="<?= url($pagination['prev']) ?>">&laquo; Prev</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link">&laquo; Prev</span>
          </li>
        <?php endif ?>

        <?php foreach ($pagination['pages'] as $p): ?>
          <?php if ($p['page'] === '...'): ?>
            <li class="page-item disabled">
              <span class="page-link">...</span>
            </li>
          <?php else: ?>
            <li class="page-item<?= $p['active'] ? ' active' : '' ?>">
              <a class="page-link" href="<?= url($p['url']) ?>">
                <?= $p['page'] ?>
              </a>
            </li>
          <?php endif ?>
        <?php endforeach ?>

        <?php if ($pagination['next']): ?>
          <li class="page-item">
            <a class="page-link" href="<?= url($pagination['next']) ?>">Next &raquo;</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link">Next &raquo;</span>
          </li>
        <?php endif ?>
      </ul>
    </nav>
  <?php endif ?>
<?php else: ?>
  <p>No articles found.</p>
<?php endif ?>

<?php require __DIR__ . '/../../after.html.php'; ?>
