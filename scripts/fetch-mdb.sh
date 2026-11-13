#!/usr/bin/env bash
# Скачивает MDB UI Kit (Standard, MIT). Использование: fetch-mdb.sh [каталог]
set -euo pipefail

VER="${MDB_VERSION:-9.3.0}"
DIR="${1:-$(cd "$(dirname "$0")/.." && pwd)/public/assets/vendor/mdb}"
BASE="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/$VER"

mkdir -p "$DIR"
curl -fsSL "$BASE/mdb.min.css" -o "$DIR/mdb.min.css"

if curl -fsSL "$BASE/mdb.umd.min.js" -o "$DIR/mdb.umd.min.js"; then
  echo "JS: mdb.umd.min.js"
elif curl -fsSL "$BASE/mdb.min.js" -o "$DIR/mdb.umd.min.js"; then
  echo "JS: mdb.min.js (сохранён как mdb.umd.min.js)"
else
  echo "Не удалось скачать JS MDB версии $VER" >&2
  exit 1
fi

ls -l "$DIR"
