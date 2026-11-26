# Start een wegwerp-WordPress (Docker) met de plugin gemonteerd, voor handmatig testen op http://localhost:8099
# Mails worden niet verstuurd maar naar .wp-test/mail/ geschreven.
$ErrorActionPreference = 'Continue'
$root = Split-Path $PSScriptRoot -Parent
$t = Join-Path $root '.wp-test'
New-Item -ItemType Directory -Force "$t\mu", "$t\mail" | Out-Null
@'
<?php
// Test-only: vang uitgaande mail op in een bestand in plaats van te versturen.
add_filter('pre_wp_mail', function ($null, $atts) {
    $f = '/mail/' . date('His') . '-' . substr(md5(uniqid('', true)), 0, 6) . '.txt';
    $att = isset($atts['attachments']) ? implode(',', array_map('basename', (array) $atts['attachments'])) : '';
    file_put_contents($f, 'TO: ' . (is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to']) . "\nSUBJECT: " . $atts['subject'] . "\nHEADERS: " . json_encode($atts['headers']) . "\nATTACHMENTS: " . $att . "\n\n" . $atts['message']);
    return true;
}, 10, 2);
'@ | ForEach-Object { [IO.File]::WriteAllText("$t\mu\capture-mail.php", $_, (New-Object Text.UTF8Encoding $false)) }

docker network inspect ow-test *> $null; if ($LASTEXITCODE -ne 0) { docker network create ow-test | Out-Null }
docker rm -f ow-db ow-wp *> $null
docker run -d --name ow-db --network ow-test -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp mariadb:11 | Out-Null
docker run -d --name ow-wp --network ow-test -p 8099:80 `
  -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp `
  -v "${root}\offerte-wizard:/var/www/html/wp-content/plugins/offerte-wizard" `
  -v "$t\mu:/var/www/html/wp-content/mu-plugins" `
  -v "$t\mail:/mail" `
  wordpress:latest | Out-Null
Write-Host 'Wachten op database...'
Start-Sleep -Seconds 25
docker run --rm --network ow-test --volumes-from ow-wp -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp --user 33:33 wordpress:cli `
  wp core install --url=http://localhost:8099 --title=Test --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
docker run --rm --network ow-test --volumes-from ow-wp -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp --user 33:33 wordpress:cli `
  wp plugin activate offerte-wizard
docker run --rm --network ow-test --volumes-from ow-wp -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp --user 33:33 wordpress:cli `
  wp post create --post_type=page --post_status=publish --post_title='Kolom test' --post_name=kolom '--post_content=<h2>Een kolom met tekst eromheen</h2><div style="max-width:420px;border:2px dashed #999;padding:10px">[offerte_wizard]</div><p>Tekst na de wizard.</p>'
docker run --rm --network ow-test --volumes-from ow-wp -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp --user 33:33 wordpress:cli `
  wp post create --post_type=page --post_status=publish --post_title='Offerte' --post_name=offerte '--post_content=[offerte_wizard fullpage="1"]'
docker run --rm --network ow-test --volumes-from ow-wp -e WORDPRESS_DB_HOST=ow-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp --user 33:33 wordpress:cli `
  wp rewrite structure '/%postname%/' --hard
Write-Host 'Klaar: http://localhost:8099/kolom/  en  http://localhost:8099/offerte/'
