#!/usr/bin/env bash
# Использование: bash scripts/deploy.sh test|prod [git-ref]
# Запускать на сервере от пользователя deploy.
set -euo pipefail

ENV_NAME="${1:?Использование: deploy.sh test|prod [ref]}"
REPO="${PLAN_REPO:-/var/www/plan-repo}"

case "$ENV_NAME" in
  test) BASE=/var/www/plan-0; BRANCH=develop ;;
  prod) BASE=/var/www/plan;   BRANCH=main ;;
  *) echo "Неизвестное окружение: $ENV_NAME" >&2; exit 1 ;;
esac
REF="${2:-origin/$BRANCH}"

[ -f "$BASE/shared/.env" ] || { echo "Нет файла $BASE/shared/.env" >&2; exit 1; }

cd "$REPO"
git fetch --prune origin
SHA="$(git rev-parse --short "$REF")"
REL="$(date +%Y%m%d-%H%M%S)-$SHA"
DEST="$BASE/releases/$REL"

mkdir -p "$DEST"
git archive "$REF" | tar -x -C "$DEST"
echo "Релиз: $REL ($REF)"

VENDOR="$BASE/shared/vendor/mdb"
[ -f "$VENDOR/mdb.min.css" ] || bash "$DEST/scripts/fetch-mdb.sh" "$VENDOR"
mkdir -p "$DEST/public/assets/vendor"
ln -sfn "$VENDOR" "$DEST/public/assets/vendor/mdb"

sudo -u www-data php "$DEST/bin/migrate.php"

ln -sfn "$DEST" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$BASE/current"
sudo systemctl reload php8.3-fpm

ls -1dt "$BASE"/releases/* | tail -n +6 | xargs -r rm -rf
echo "Готово: $BASE/current -> $REL"
