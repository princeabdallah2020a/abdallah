#!/usr/bin/env bash
# Lint every PHP file, then run the standalone test suite. Usage: bin/check.sh [test-filter]
set -euo pipefail
cd "$(dirname "$0")/.."
echo "== php -l =="
fail=0
while IFS= read -r f; do
  out=$(php -l "$f" 2>&1) || { echo "$out"; fail=1; }
done < <(find . -name '*.php' -not -path './tests/shim/*' | sort)
if [ "$fail" -eq 0 ]; then echo "all files OK"; else echo "LINT FAILED"; exit 1; fi
echo "== tests =="
php tests/run.php "${1:-}"
