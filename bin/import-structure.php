<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Db;
use App\Env;
use App\StructureTransfer;

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$deactivateMissing = !in_array('--keep-missing', $args, true);
$files = array_values(array_filter($args, fn($a) => $a !== '' && $a[0] !== '-'));

if (!$files) {
    fwrite(STDERR, "Использование: php bin/import-structure.php FILE.json [--dry-run] [--keep-missing]\n");
    exit(2);
}

Env::load();

$raw = @file_get_contents($files[0]);
$data = $raw === false ? null : json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Не удалось прочитать JSON из {$files[0]}\n");
    exit(1);
}

$transfer = new StructureTransfer();
$errors = $transfer->validate($data);
if ($errors) {
    fwrite(STDERR, "Файл не прошёл проверку:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

if (!$dry) {
    $dir = Env::sharedDir() . '/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $dest = $dir . '/pre-import-' . date('Ymd-His') . '.sqlite';
    $pdo = Db::pdo();
    $pdo->exec('VACUUM INTO ' . $pdo->quote($dest));
    echo "Резервная копия базы: $dest\n";
}

try {
    $rep = $transfer->import($data, $dry, $deactivateMissing, 'cli:' . get_current_user());
} catch (Throwable $e) {
    fwrite(STDERR, 'ОШИБКА: ' . $e->getMessage() . "\nИзменения не применены.\n");
    exit(1);
}

$names = ['units' => 'Подразделения', 'positions' => 'Должности', 'employees' => 'Сотрудники'];
echo $dry ? "ПРОБНЫЙ ЗАПУСК (ничего не изменено)\n" : "ИМПОРТ ВЫПОЛНЕН\n";
foreach ($names as $k => $label) {
    $r = $rep[$k];
    printf("%-14s создано: %d, изменено: %d, без изменений: %d, отключено: %d\n",
        $label, $r['created'], $r['updated'], $r['unchanged'], $r['deactivated']);
}
echo "Руководителей изменено: {$rep['heads_changed']}\n";
if (!$deactivateMissing) {
    echo "(режим --keep-missing: отсутствующие в файле записи не отключались)\n";
}
