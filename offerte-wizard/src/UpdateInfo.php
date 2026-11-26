<?php
namespace OfferteWizard;

/** Leest en controleert het update-bestand (JSON) dat de plugin op een eigen URL opvraagt. */
final class UpdateInfo
{
    /** https altijd; http alleen voor lokaal testen (localhost, 127.0.0.1, *.test). */
    public static function urlAllowed(string $url): bool
    {
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host']) || empty($p['scheme'])) {
            return false;
        }
        if ($p['scheme'] === 'https') {
            return true;
        }
        return $p['scheme'] === 'http'
            && (in_array($p['host'], ['localhost', '127.0.0.1'], true) || (bool) preg_match('/\.(test|localhost)$/', $p['host']));
    }

    /** @return array{version:string,download_url:string,sha256:string,changelog:string}|null */
    public static function parse(string $json): ?array
    {
        $d = json_decode($json, true);
        if (!is_array($d)) {
            return null;
        }
        $version = (string) ($d['version'] ?? '');
        $download = (string) ($d['download_url'] ?? '');
        $sha = strtolower((string) ($d['sha256'] ?? ''));
        if (!preg_match('/^\d+(\.\d+){1,3}$/', $version) || !self::urlAllowed($download)) {
            return null;
        }
        if ($sha !== '' && !preg_match('/^[a-f0-9]{64}$/', $sha)) {
            return null;
        }
        return [
            'version' => $version,
            'download_url' => $download,
            'sha256' => $sha,
            'changelog' => mb_substr(trim(strip_tags((string) ($d['changelog'] ?? ''))), 0, 3000),
        ];
    }

    public static function isNewer(string $current, array $info): bool
    {
        return version_compare($info['version'], $current, '>');
    }
}
