# План

Система учёта задач для небольшого издательства. Этап 1 — структура компании
(дирекция, отделы, должности, сотрудники).

## Окружения

| | Тест | Боевой |
|---|---|---|
| Домен | xplan.tlti.ru | plan.tlti.ru |
| Ветка | develop | main |
| Каталог | /var/www/plan-0 | /var/www/plan |
| Редактор структуры | только godoftasks | отключён |

## Деплой (на сервере, от deploy)

    git -C /var/www/plan-repo pull --ff-only
    bash /var/www/plan-repo/scripts/deploy.sh test
    bash /var/www/plan-repo/scripts/deploy.sh prod

## Перенос структуры с теста на боевой

1. На тесте: «Экспорт JSON» (с датами рождения или без).
2. Загрузить файл на сервер, например в /tmp/structure.json.
3. Проверить и применить:

        sudo -u www-data php /var/www/plan/current/bin/import-structure.php /tmp/structure.json --dry-run
        sudo -u www-data php /var/www/plan/current/bin/import-structure.php /tmp/structure.json

Перед применением автоматически создаётся копия базы в shared/backups.
Записи, которых нет в файле, отключаются (не удаляются); флаг --keep-missing это отменяет.
