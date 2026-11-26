<?php
namespace OfferteWizard;

final class Plugin
{
    public static function boot(): void
    {
        add_action('init', [Storage::class, 'register']);
        add_action('rest_api_init', [Rest::class, 'register']);
        Shortcode::register();
        Inbox::register();
        Updater::register();
        add_action('ow_cleanup', [Uploads::class, 'cleanup']);
        if (is_admin()) {
            Admin::register();
        }
    }

    public static function activate(): void
    {
        Uploads::ensureDir();
        if (!wp_next_scheduled('ow_cleanup')) {
            wp_schedule_event(time() + 3600, 'daily', 'ow_cleanup');
        }
        if (get_option(Settings::OPTION, null) === null) {
            add_option(Settings::OPTION, Settings::defaults(), '', false);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('ow_cleanup');
    }
}
