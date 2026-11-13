<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title ?? 'План') ?> — План</title>
  <link rel="stylesheet" href="/assets/vendor/mdb/mdb.min.css">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/">План</a>
    <?php if (\App\Env::get('APP_ENV') === 'test'): ?>
      <span class="badge badge-danger me-auto">тестовое окружение</span>
    <?php endif; ?>
    <ul class="navbar-nav ms-auto flex-row gap-3">
      <li class="nav-item"><a class="nav-link" href="/structure">Структура</a></li>
      <?php if (\App\Guard::canEdit()): ?>
        <li class="nav-item"><a class="nav-link" href="/admin/structure">Редактор</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/structure/export">Экспорт JSON</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<main class="container pb-5">
  <?php foreach ($flashes as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="alert"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<script src="/assets/vendor/mdb/mdb.umd.min.js"></script>
</body>
</html>
