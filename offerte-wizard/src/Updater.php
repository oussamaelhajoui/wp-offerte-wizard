<?php
namespace OfferteWizard;

/**
 * Updates via een eigen URL: de plugin vraagt een klein JSON-bestand op en WordPress toont dan
 * "Update beschikbaar" bij Plugins, met één klik bijwerken. De download wordt gecontroleerd met een SHA-256.
 */
final class Updater
{
    const TRANSIENT = 'ow_update_info';
    const SLUG = 'offerte-wizard';

    public static function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'inject']);
        add_filter('plugins_api', [self::class, 'details'], 10, 3);
        add_filter('upgrader_pre_download', [self::class, 'verifiedDownload'], 10, 3);
        add_action('admin_post_ow_check_update', [self::class, 'checkNow']);
    }

    public static function basename(): string
    {
        return plugin_basename(OW_FILE);
    }

    /** @return array|null laatste bekende release volgens de update-URL */
    public static function remote(bool $force = false): ?array
    {
        $url = (string) Settings::get()['update_url'];
        if ($url === '' || !UpdateInfo::urlAllowed($url)) {
            return null;
        }
        if (!$force) {
            $c = get_transient(self::TRANSIENT);
            if (is_array($c) && ($c['url'] ?? '') === $url) {
                return $c['info'];
            }
        }
        $r = wp_remote_get($url, ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]);
        $info = (!is_wp_error($r) && (int) wp_remote_retrieve_response_code($r) === 200)
            ? UpdateInfo::parse((string) wp_remote_retrieve_body($r))
            : null;
        set_transient(self::TRANSIENT, ['url' => $url, 'info' => $info], $info ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
        return $info;
    }

    /** @param mixed $transient */
    public static function inject($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }
        $info = self::remote();
        if ($info === null) {
            return $transient;
        }
        $item = (object) [
            'id' => self::basename(),
            'slug' => self::SLUG,
            'plugin' => self::basename(),
            'new_version' => $info['version'],
            'url' => '',
            'package' => $info['download_url'],
            'requires_php' => '7.4',
            'icons' => [],
        ];
        if (UpdateInfo::isNewer(OW_VERSION, $info)) {
            $transient->response[self::basename()] = $item;
        } else {
            $transient->no_update[self::basename()] = $item;
        }
        return $transient;
    }

    /** Het venster "Details bekijken" bij een beschikbare update. @param mixed $res */
    public static function details($res, $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== self::SLUG) {
            return $res;
        }
        $info = self::remote();
        if ($info === null) {
            return $res;
        }
        return (object) [
            'name' => 'Offerte Wizard',
            'slug' => self::SLUG,
            'version' => $info['version'],
            'download_link' => $info['download_url'],
            'requires_php' => '7.4',
            'sections' => ['changelog' => nl2br(esc_html($info['changelog'] !== '' ? $info['changelog'] : 'Geen toelichting.'))],
        ];
    }

    /**
     * Downloadt de update zelf en vergelijkt de SHA-256 met het update-bestand; klopt die niet,
     * dan wordt er niets geïnstalleerd.
     * @param mixed $reply
     * @return mixed
     */
    public static function verifiedDownload($reply, $package, $upgrader)
    {
        if ($reply !== false || !is_string($package)) {
            return $reply;
        }
        $info = self::remote();
        if ($info === null || $info['sha256'] === '' || $package !== $info['download_url']) {
            return $reply;
        }
        $file = download_url($package, 300);
        if (is_wp_error($file)) {
            return $file;
        }
        if (!hash_equals($info['sha256'], (string) hash_file('sha256', $file))) {
            @unlink($file);
            return new \WP_Error('ow_hash', 'De gedownloade update komt niet overeen met de verwachte checksum. De update is afgebroken.');
        }
        return $file;
    }

    public static function checkNow(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die('Geen toegang.');
        }
        check_admin_referer('ow_check_update');
        delete_transient(self::TRANSIENT);
        delete_site_transient('update_plugins');
        $info = self::remote(true);
        wp_update_plugins();
        $state = $info === null ? 'fout' : (UpdateInfo::isNewer(OW_VERSION, $info) ? 'nieuw' : 'actueel');
        wp_safe_redirect(admin_url('options-general.php?page=offerte-wizard&ow_upd=' . $state));
        exit;
    }
}
