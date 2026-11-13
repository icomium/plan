<?php
$code = $code ?? 500;
$message = $message ?? match ($code) {
    404 => 'Страница не найдена.',
    405 => 'Метод не поддерживается.',
    default => 'Произошла ошибка.',
};
?>
<div class="alert alert-warning mt-4">
  <strong><?= (int)$code ?></strong> — <?= e($message) ?>
</div>
<a href="/structure" class="btn btn-outline-primary btn-sm">К структуре</a>
