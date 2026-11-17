#!/usr/bin/env bash
# Lint every PHP file, then run the plugin suite and the cross-plugin integration suite.
# pipefail: a failing suite piped through `tail` must still fail this script (SECURITY-REVIEW M5).
set -u -o pipefail
cd "$(dirname "$0")/.."
status=0
echo "== php -l =="
while IFS= read -r f; do
  out=$(php -l "$f" 2>&1) || { echo "$out"; status=1; }
done < <(find . -name '*.php' -not -path './.git/*' | sort)
if [ $status -eq 0 ]; then echo "all files lint clean"; fi
echo "== unit/service/sync suite =="
php tests/run.php | tail -n 12 || status=1
echo "== cross-plugin integration (needs ../wotoyi-marketplace) =="
php tests/integration/run.php | tail -n 12 || status=1
echo "== missing-key behaviour =="
WOTOYI_TEST_NO_KEY=1 php tests/scripts/no_key.php || status=1
echo
if [ $status -ne 0 ]; then echo "CHECK FAILED"; else echo "CHECK PASSED"; fi
exit $status
