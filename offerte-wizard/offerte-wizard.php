<?php
/**
 * Plugin Name: Offerte Wizard
 * Description: Stap-voor-stap offerteaanvraag met prijsindicatie en bouwplan-uitlezing. Plaatsbaar via een shortcode in een column of op een volledige pagina; de stijl is volledig afgeschermd van het thema.
 * Version: 1.5.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Afbouw Megens
 * License: GPL-2.0-or-later
 * Text Domain: offerte-wizard
 */

defined('ABSPATH') || exit;

define('OW_VERSION', '1.5.1');
define('OW_FILE', __FILE__);
define('OW_DIR', plugin_dir_path(__FILE__));

spl_autoload_register(static function ($class) {
    $prefix = 'OfferteWizard\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = OW_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, ['OfferteWizard\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['OfferteWizard\\Plugin', 'deactivate']);

OfferteWizard\Plugin::boot();
