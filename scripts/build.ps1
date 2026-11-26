# Bouwt build/offerte-wizard.zip en het update-bestand build/offerte-wizard.json.
# Gebruik:  .\scripts\build.ps1 -BaseUrl https://github.com/OWNER/REPO/releases/latest/download
#   (BaseUrl = de map waar je zip en json zet; het update-bestand verwijst daar naar de zip.)
# Controleert eerst lint en tests, zodat er nooit een kapotte zip ontstaat.
param([string]$BaseUrl = '')
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
& "$PSScriptRoot\lint.ps1"
if ($LASTEXITCODE -ne 0) { throw 'Lint mislukt' }
& "$PSScriptRoot\test.ps1"
if ($LASTEXITCODE -ne 0) { throw 'Tests mislukt' }

New-Item -ItemType Directory -Force "$root\build" | Out-Null
$zip = "$root\build\offerte-wizard.zip"
if (Test-Path $zip) { Remove-Item $zip }
# bsdtar maakt een echte zip met forward slashes (Compress-Archive schrijft backslashes, dat breekt op Linux-hosting).
tar -a -c -f $zip -C $root offerte-wizard

# Update-bestand: versie uit de plugin-header, checksum van de zip, toelichting uit readme.txt.
$main = Get-Content "$root\offerte-wizard\offerte-wizard.php" -Raw
$version = [regex]::Match($main, '\* Version:\s*([0-9.]+)').Groups[1].Value
if (-not $version) { throw 'Versie niet gevonden in offerte-wizard.php' }
$readme = Get-Content "$root\offerte-wizard\readme.txt" -Raw
$m = [regex]::Match($readme, "(?ms)^= $([regex]::Escape($version)) =\s*\r?\n(.*?)(?=^= |\z)")
$changelog = if ($m.Success) { $m.Groups[1].Value.Trim() } else { '' }
$sha = (Get-FileHash $zip -Algorithm SHA256).Hash.ToLower()
$base = if ($BaseUrl) { $BaseUrl.TrimEnd('/') } else { 'https://VUL-HIER-JE-URL-IN' }
$json = [ordered]@{ version = $version; download_url = "$base/offerte-wizard.zip"; sha256 = $sha; changelog = $changelog } | ConvertTo-Json
[IO.File]::WriteAllText("$root\build\offerte-wizard.json", $json, (New-Object Text.UTF8Encoding $false))
Write-Host "Klaar: versie $version"
Write-Host "  $zip"
Write-Host "  $root\build\offerte-wizard.json"
if (-not $BaseUrl) { Write-Host 'Let op: geef -BaseUrl mee, anders verwijst het json-bestand naar een plaatshouder.' }
