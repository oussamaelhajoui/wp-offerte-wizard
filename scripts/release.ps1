# Brengt de huidige versie uit: bouwt zip + update-bestand, pusht naar GitHub en maakt een release met beide bijlagen.
# Vereist: gh (ingelogd) en SSH-toegang tot de repo. Verhoog eerst het versienummer (offerte-wizard.php + readme.txt).
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$repo = 'oussamaelhajoui/wp-offerte-wizard'
Set-Location $root

& "$PSScriptRoot\build.ps1" -BaseUrl "https://github.com/$repo/releases/latest/download"

# git schrijft waarschuwingen naar stderr; die zijn geen fout, alleen de exitcode telt.
$ErrorActionPreference = 'Continue'
function Invoke-Git {
    & git @args
    if ($LASTEXITCODE -ne 0) { throw "git $($args -join ' ') mislukt (exit $LASTEXITCODE)" }
}

$main = Get-Content "$root\offerte-wizard\offerte-wizard.php" -Raw
$version = [regex]::Match($main, '\* Version:\s*([0-9.]+)').Groups[1].Value
$tag = "v$version"
$json = Get-Content "$root\build\offerte-wizard.json" -Raw | ConvertFrom-Json

if (git tag --list $tag) { throw "Tag $tag bestaat al: verhoog eerst het versienummer." }
Invoke-Git add -A
if (git status --porcelain) {
    Invoke-Git commit -m "release: $version" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
}
Invoke-Git push -u origin HEAD
Invoke-Git tag $tag
Invoke-Git push origin $tag

gh release create $tag "$root\build\offerte-wizard.zip" "$root\build\offerte-wizard.json" --repo $repo --title "Offerte Wizard $version" --notes $json.changelog --latest
if ($LASTEXITCODE -ne 0) { throw 'gh release create mislukt' }
Write-Host "Release $tag staat online: https://github.com/$repo/releases/tag/$tag"
