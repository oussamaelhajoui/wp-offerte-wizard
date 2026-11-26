# Test van de update-functie: WordPress met de gebouwde zip (versie X) en een lokale update-server die X+0.0.1 aanbiedt.
# Draait volledig in Docker; raakt de bronmap niet (de plugin wordt uit de zip geinstalleerd, niet gemonteerd).
$ErrorActionPreference = 'Continue'
$root = Split-Path $PSScriptRoot -Parent
$t = Join-Path $root '.wp-test'
Remove-Item -Recurse -Force "$t\zips", "$t\updates", "$t\next" -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force "$t\zips", "$t\updates", "$t\next" | Out-Null
Copy-Item "$root\build\offerte-wizard.zip" "$t\zips\installed.zip"

# "Volgende versie": kopie met versie +0.0.1
$main = Get-Content "$root\offerte-wizard\offerte-wizard.php" -Raw
$cur = [regex]::Match($main, '\* Version:\s*([0-9]+)\.([0-9]+)\.([0-9]+)')
$next = "$($cur.Groups[1].Value).$($cur.Groups[2].Value).$([int]$cur.Groups[3].Value + 1)"
Copy-Item -Recurse "$root\offerte-wizard" "$t\next\offerte-wizard"
$p = "$t\next\offerte-wizard\offerte-wizard.php"
$c = (Get-Content $p -Raw) -replace '\* Version:\s*[0-9.]+', "* Version: $next" -replace "define\('OW_VERSION', '[0-9.]+'\)", "define('OW_VERSION', '$next')"
[IO.File]::WriteAllText($p, $c, (New-Object Text.UTF8Encoding $false))
tar -a -c -f "$t\updates\offerte-wizard.zip" -C "$t\next" offerte-wizard
$sha = (Get-FileHash "$t\updates\offerte-wizard.zip" -Algorithm SHA256).Hash.ToLower()
function Write-Json($hash) {
  $j = [ordered]@{ version = $next; download_url = 'http://updates.test/offerte-wizard.zip'; sha256 = $hash; changelog = "Testupdate $next" } | ConvertTo-Json
  [IO.File]::WriteAllText("$t\updates\offerte-wizard.json", $j, (New-Object Text.UTF8Encoding $false))
}
Write-Json $sha

# Alleen voor deze test: WordPress weigert downloads van interne (Docker-)adressen; de testhost wordt daarom toegestaan.
New-Item -ItemType Directory -Force "$t\mu-update" | Out-Null
[IO.File]::WriteAllText("$t\mu-update\allow-test-host.php", "<?php`nadd_filter('http_request_host_is_external', function (`$ext, `$host) { return `$host === 'updates.test' ? true : `$ext; }, 10, 2);`n", (New-Object Text.UTF8Encoding $false))

docker network inspect ow-test *> $null; if ($LASTEXITCODE -ne 0) { docker network create ow-test | Out-Null }
docker rm -f ow-db ow-wp ow-up *> $null
docker run -d --name ow-db --network ow-test -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp mariadb:11 | Out-Null
docker run -d --name ow-up --network ow-test --network-alias updates.test -v "$t\updates:/srv" php:8.4-cli php -S 0.0.0.0:80 -t /srv | Out-Null
docker run -d --name ow-wp --network ow-test -p 8099:80 -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp -v "$t\zips:/zips" -v "$t\mu-update:/var/www/html/wp-content/mu-plugins" wordpress:latest | Out-Null
Start-Sleep -Seconds 25
$wp = 'docker run --rm --network ow-test --volumes-from ow-wp -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp --user 33:33 wordpress:cli wp'
Invoke-Expression "$wp core install --url=http://localhost:8099 --title=Test --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email" | Select-Object -Last 1
Invoke-Expression "$wp plugin install /zips/installed.zip --activate" | Select-Object -Last 2
Invoke-Expression "$wp option patch update ow_settings update_url http://updates.test/offerte-wizard.json" | Select-Object -Last 1

"--- 1. na installatie (verwacht: versie $($cur.Groups[0].Value -replace '.*:\s*','') met update naar $next)"
Invoke-Expression "$wp plugin list --name=offerte-wizard --fields=name,status,version,update,update_version"
"--- 2. FOUTE checksum: update moet worden geweigerd"
Write-Json ('0' * 64)
Invoke-Expression "$wp transient delete ow_update_info" | Out-Null
Invoke-Expression "$wp plugin update offerte-wizard" 2>&1 | Select-Object -Last 4
Invoke-Expression "$wp plugin get offerte-wizard --field=version"
"--- 3. juiste checksum: update moet lukken"
Write-Json $sha
Invoke-Expression "$wp transient delete ow_update_info" | Out-Null
Invoke-Expression "$wp plugin update offerte-wizard" 2>&1 | Select-Object -Last 4
Invoke-Expression "$wp plugin list --name=offerte-wizard --fields=name,status,version,update"
