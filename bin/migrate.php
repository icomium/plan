<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Db;
use App\Env;

Env::load();
$pdo = Db::pdo();

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    name TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
)");
$done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(ROOT . '/migrations/*.sql') ?: [];
sort($files);
$applied = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec((string)file_get_contents($file));
        $pdo->prepare('INSERT INTO schema_migrations(name) VALUES (?)')->execute([$name]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "Миграция $name не применена: " . $e->getMessage() . "\n");
        exit(1);
    }
    echo "Применена: $name\n";
    $applied++;
}
echo $applied ? "Готово: применено миграций — $applied\n" : "Новых миграций нет\n";
