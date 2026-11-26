# php -l over alle PHP-bestanden van de plugin (PHP 8.4-container).
$root = Split-Path $PSScriptRoot -Parent
docker run --rm -v "${root}:/app" -w /app php:8.4-cli sh scripts/lint.sh
