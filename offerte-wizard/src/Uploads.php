<?php
namespace OfferteWizard;

/** Afgeschermde opslag van bouwplannen met willekeurige bestandsnamen. */
final class Uploads
{
    public static function dir(): string
    {
        $u = wp_upload_dir();
        return trailingslashit($u['basedir']) . 'offerte-wizard/';
    }

    public static function ensureDir(): void
    {
        $d = self::dir();
        wp_mkdir_p($d);
        if (!is_file($d . 'index.php')) {
            file_put_contents($d . 'index.php', "<?php\n// Silence is golden.\n");
        }
        if (!is_file($d . '.htaccess')) {
            file_put_contents($d . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
    }

    /**
     * @param array $file een entry uit $_FILES / WP_REST_Request::get_file_params()
     * @return array{token:string,path:string,ext:string}
     * @throws \RuntimeException met een melding voor de klant
     */
    public static function store(array $file, int $maxBytes): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Het uploaden is mislukt. Probeer het opnieuw.');
        }
        $size = (int) ($file['size'] ?? 0);
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 16);
        $ext = UploadPolicy::sniff($head);
        $err = UploadPolicy::check($size, $maxBytes, $ext);
        if ($err !== null) {
            throw new \RuntimeException($err);
        }
        self::ensureDir();
        $token = bin2hex(random_bytes(16));
        $path = self::dir() . $token . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('Het bestand kon niet worden opgeslagen.');
        }
        @chmod($path, 0640);
        return ['token' => $token, 'path' => $path, 'ext' => $ext];
    }

    public static function find(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $hits = glob(self::dir() . $token . '.*') ?: [];
        return $hits ? $hits[0] : null;
    }

    public static function cleanup(): void
    {
        $days = (int) Settings::get()['retention_days'];
        $limit = time() - $days * DAY_IN_SECONDS;
        foreach (glob(self::dir() . '*') ?: [] as $f) {
            $base = basename($f);
            if (preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png|webp)$/', $base) && is_file($f) && filemtime($f) < $limit) {
                @unlink($f);
            }
        }
    }
}
