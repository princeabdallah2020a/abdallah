#!/usr/bin/env bash
# php -l on every file, then the demo test suite. Usage: bin/check.sh [filter]
set -euo pipefail
cd "$(dirname "$0")/.."
echo "== php -l =="
fail=0
while IFS= read -r f; do
  out=$(php -l "$f" 2>&1) || { echo "$out"; fail=1; }
done < <(find . -name '*.php' | sort)
[ "$fail" -eq 0 ] && echo "all files OK"
echo "== tests =="
php tests/run.php "${1:-}"
