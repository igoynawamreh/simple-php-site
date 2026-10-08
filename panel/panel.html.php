<?php

require_once __DIR__ . '/helper/auth.php';

// When `$state['env']` (in "config.php") is `development`, the Vite dev
// server must be running (`npm run dev`) for this page to load
// correctly — it serves the unbuilt assets referenced below instead of
// the production build.
//
// To skip running the dev server, set `$state['env']` (in "config.php")
// to `production` instead, and run `npm run build` every time the code changes.
$isDev = $state['env'] === 'development';

$path = $page['route:rest'] ?? 'index';

// Custom API routes
if (str_starts_with($path, 'api/')) {
  // Deny by default: every endpoint needs a login, except these two.
  // (The comparison is exact, so variants like "api/login/" or
  // "api/./login" are treated as protected.)
  if (!in_array($path, ['api/login', 'api/logout'], true)) {
    if (!auth_is_logged_in()) {
      json_error('Unauthorized.', 401);
    }
    session_write_close(); // don't hold the session lock during the request
  }

  $file = __DIR__ . '/' . trim($path, '/') . '.php';
  if (is_file($file)) {
    require $file;
    exit;
  }
}

$authenticated = auth_is_logged_in();
session_write_close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page['title']) ?> - <?= e($site['title']) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="<?= url('/favicon.ico') ?>">

    <script>
      window.APP = <?= json_encode([
        'isDev' => $isDev,
        'SITE_TITLE' => $site['title'],
        'BASE_URL' => $base_url,
        'HOME_URL' => $home_url,
        'AUTHENTICATED' => $authenticated,
      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>

    <?php if ($isDev): ?>
      <script type="module" src="http://localhost:5173/@vite/client"></script>
      <script type="module" src="http://localhost:5173/src/main.js"></script>
    <?php else: ?>
      <link rel="stylesheet" href="<?= url('/panel/build/css/main.css') ?>">
      <script type="module" src="<?= url('/panel/build/js/main.js') ?>"></script>
    <?php endif; ?>
</head>
<body>
  <div id="app"></div>
</body>
</html>
