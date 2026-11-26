# Draait de unit-tests in een wegwerp-PHP-container (geen lokale PHP nodig).
$root = Split-Path $PSScriptRoot -Parent
docker run --rm -v "${root}:/app" -w /app php:8.4-cli vendor/bin/phpunit @args
