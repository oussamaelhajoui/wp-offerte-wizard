<?php
// Opruimen bij verwijderen van de plugin: instellingen en uploads. Aanvragen (privé berichten) blijven bewaard.
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('ow_settings');
$upload = wp_upload_dir();
$dir = trailingslashit($upload['basedir']) . 'offerte-wizard/';
if (is_dir($dir)) {
    foreach (glob($dir . '{*,.htaccess}', GLOB_BRACE) ?: [] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    @rmdir($dir);
}
wp_clear_scheduled_hook('ow_cleanup');
