<?php
namespace OfferteWizard;

final class Shortcode
{
    public static function register(): void
    {
        add_shortcode('offerte_wizard', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'maybeEnqueue']);
    }

    /** Laadt het script alleen waar de wizard staat (of overal, als dat is ingesteld). */
    public static function maybeEnqueue(): void
    {
        $load = !empty(Settings::get()['load_everywhere']);
        if (!$load && is_singular()) {
            $post = get_post();
            if ($post && (has_shortcode($post->post_content, 'offerte_wizard') || strpos($post->post_content, 'data-offerte-wizard') !== false)) {
                $load = true;
            }
        }
        if ($load) {
            self::enqueue();
        }
    }

    public static function enqueue(): void
    {
        $js = OW_DIR . 'assets/wizard.js';
        $ver = OW_VERSION . '.' . (is_file($js) ? filemtime($js) : '0');
        wp_enqueue_script('offerte-wizard', plugins_url('assets/wizard.js', OW_FILE), [], $ver, true);
        $cfg = [
            'rest' => esc_url_raw(rest_url(Rest::NS . '/')),
            'css' => plugins_url('assets/wizard.css', OW_FILE) . '?v=' . $ver,
        ];
        wp_add_inline_script('offerte-wizard', 'window.OfferteWizardConfig=' . wp_json_encode($cfg) . ';', 'before');
    }

    public static function render($atts): string
    {
        $a = shortcode_atts(['fullpage' => '0', 'mode' => '', 'bouwplan' => 'aan'], is_array($atts) ? $atts : [], 'offerte_wizard');
        $mode = in_array($a['mode'], Settings::MODES, true) ? $a['mode'] : '';
        self::enqueue();
        return '<offerte-wizard data-offerte-wizard style="display:block" data-fullpage="' . ($a['fullpage'] === '1' ? '1' : '0')
            . '" data-mode="' . esc_attr($mode)
            . '" data-bouwplan="' . ($a['bouwplan'] === 'uit' ? 'uit' : 'aan') . '"></offerte-wizard>'
            . '<noscript>Schakel JavaScript in om een offerte aan te vragen.</noscript>';
    }
}
