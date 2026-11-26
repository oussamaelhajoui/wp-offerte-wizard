#!/bin/sh
# php -l over alle PHP-bestanden van de plugin; draait in de PHP-container.
cd "$(dirname "$0")/.." || exit 1
fail=0
for f in $(find offerte-wizard -name '*.php'); do
  out=$(php -l "$f" 2>&1) || { echo "$out"; fail=1; }
done
[ $fail -eq 0 ] && echo "lint ok"
exit $fail
