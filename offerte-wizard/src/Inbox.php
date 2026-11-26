<?php
namespace OfferteWizard;

/**
 * Het inzendingen-scherm in wp-admin en de knoppen uit de mail aan de eigenaar:
 * offerte direct versturen, eigen prijs invullen en versturen, of zelf afhandelen.
 */
final class Inbox
{
    public static function register(): void
    {
        add_action('init', [self::class, 'handlePublic'], 5);
        add_action('add_meta_boxes_' . Storage::POST_TYPE, [self::class, 'metaboxes']);
        add_action('admin_footer-post.php', [self::class, 'footerForm']);
        add_action('admin_post_ow_send_quote', [self::class, 'adminSend']);
        add_action('admin_post_ow_mark_done', [self::class, 'adminDone']);
        add_action('admin_notices', [self::class, 'notice']);
        add_filter('manage_' . Storage::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . Storage::POST_TYPE . '_posts_custom_column', [self::class, 'column'], 10, 2);
    }

    /** Ondertekende link voor de knop "Offerte sturen naar klant" in de mail. */
    public static function sendLink(int $id): string
    {
        $exp = time() + ActionToken::TTL;
        return add_query_arg([
            'ow_action' => 'send',
            'id' => $id,
            'exp' => $exp,
            'sig' => ActionToken::sign($id, 'send', $exp, wp_salt('auth')),
        ], home_url('/'));
    }

    /**
     * De link uit de mail opent eerst een bevestigingspagina; pas na een klik op de knop (POST)
     * gaat de mail weg. Zo versturen mailscanners die links vooraf openen niets per ongeluk.
     */
    public static function handlePublic(): void
    {
        if (($_GET['ow_action'] ?? '') !== 'send') {
            return;
        }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        $id = (int) ($_GET['id'] ?? 0);
        $exp = (int) ($_GET['exp'] ?? 0);
        $sig = (string) ($_GET['sig'] ?? '');
        $data = Storage::data($id);
        if ($data === null || !ActionToken::verify($id, 'send', $exp, $sig, wp_salt('auth'), time())) {
            self::page('Link ongeldig of verlopen', '<p>Open de aanvraag in wp-admin om de offerte te versturen.</p>');
        }
        $status = Storage::status($id);
        if ($status === Storage::STATUS_SENT) {
            self::page('Al verstuurd', '<p>De offerte is al naar ' . esc_html($data['email']) . ' gestuurd.</p>');
        }
        if ($status === Storage::STATUS_DONE) {
            self::page('Zelf afgehandeld', '<p>Deze aanvraag is als zelf afgehandeld gemarkeerd. Open de aanvraag in wp-admin om toch een offerte te sturen.</p>');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ow_confirm'])) {
            [$ok, $msg] = QuoteSender::send($id, []);
            self::page($ok ? 'Offerte verstuurd' : 'Niet verstuurd', '<p>' . esc_html($msg) . '</p>');
        }
        $prices = QuotePrices::resolve($data['pricing'], []);
        $html = '<p>Je staat op het punt de offerte te mailen aan <strong>' . esc_html(trim($data['first_name'] . ' ' . $data['last_name'])) . '</strong> (' . esc_html($data['email']) . ').</p><ul>';
        foreach (['stucwerk' => 'Stucwerk', 'schilderwerk' => 'Schilderwerk'] as $k => $label) {
            if ($prices[$k]) {
                $html .= '<li>' . esc_html($label) . ': €' . number_format($prices[$k]['min'], 0, ',', '.') . ' - €' . number_format($prices[$k]['max'], 0, ',', '.') . '</li>';
            }
        }
        $html .= '</ul><form method="post" action=""><button type="submit" name="ow_confirm" value="1">Ja, verstuur de offerte</button></form>'
            . '<p style="font-size:14px;color:#5b6678">Liever een eigen prijs invullen? Open de aanvraag in wp-admin.</p>';
        self::page('Offerte versturen?', $html);
    }

    /** @return never */
    private static function page(string $title, string $bodyHtml): void
    {
        status_header(200);
        echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>' . esc_html($title) . '</title><style>'
            . 'body{font:16px/1.5 system-ui,sans-serif;background:#eef5fb;color:#07172f;margin:0;padding:16px}'
            . 'main{max-width:520px;margin:32px auto;background:#fff;border-radius:16px;padding:24px}'
            . 'h1{font-size:22px;margin:0 0 12px}ul{padding-left:20px}'
            . 'button{width:100%;min-height:52px;border:0;border-radius:99px;background:#07172f;color:#fff;font:700 16px system-ui,sans-serif;cursor:pointer}'
            . '</style></head><body><main><h1>' . esc_html($title) . '</h1>' . $bodyHtml . '</main></body></html>';
        exit;
    }

    public static function metaboxes(): void
    {
        add_meta_box('ow_details', 'Aanvraag', [self::class, 'boxDetails'], Storage::POST_TYPE, 'normal', 'high');
        add_meta_box('ow_quote', 'Offerte naar klant', [self::class, 'boxQuote'], Storage::POST_TYPE, 'normal', 'high');
    }

    public static function boxDetails(\WP_Post $post): void
    {
        echo '<pre style="white-space:pre-wrap;font:13px/1.5 ui-monospace,monospace;margin:0">' . esc_html($post->post_content) . '</pre>';
    }

    public static function boxQuote(\WP_Post $post): void
    {
        $id = (int) $post->ID;
        $data = Storage::data($id);
        $status = Storage::status($id);
        if ($data === null) {
            echo '<p>Geen gegevens gevonden.</p>';
            return;
        }
        $labels = [Storage::STATUS_NEW => 'Nieuw: er is nog niets naar de klant gestuurd', Storage::STATUS_SENT => 'Offerte verstuurd', Storage::STATUS_DONE => 'Zelf afgehandeld'];
        echo '<p><strong>Status:</strong> ' . esc_html($labels[$status]);
        $sent = (int) get_post_meta($id, '_ow_sent_at', true);
        if ($sent) {
            echo ' op ' . esc_html(wp_date('j F Y H:i', $sent));
        }
        echo '</p>';
        $calc = $data['pricing'];
        if ($calc) {
            echo '<p class="description">Berekende indicatie:';
            if ($calc['stucwerk']) {
                echo ' stucwerk €' . (int) $calc['stucwerk']['min'] . ' - €' . (int) $calc['stucwerk']['max'] . ';';
            }
            if ($calc['schilderwerk']) {
                echo ' schilderwerk €' . (int) $calc['schilderwerk']['min'] . ' - €' . (int) $calc['schilderwerk']['max'] . ';';
            }
            echo ' leeg laten = deze bedragen versturen.</p>';
        } else {
            echo '<p class="description">Er is geen indicatie berekend (m² onbekend); vul hieronder zelf een prijs in.</p>';
        }
        echo '<p class="description">Eén bedrag invullen = vaste prijs; twee bedragen = van/tot. Bedragen in euro, inclusief arbeid, materiaal en BTW.</p>';
        $f = static function (string $name, string $label): void {
            echo '<p><label>' . esc_html($label) . '<br><input type="text" form="ow-quote-form" name="ow_quote[' . esc_attr($name) . ']" class="regular-text" inputmode="decimal" autocomplete="off"></label></p>';
        };
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px;max-width:640px">';
        $f('st_min', 'Stucwerk: vanaf (€)');
        $f('st_max', 'Stucwerk: tot (€)');
        $f('sc_min', 'Schilderwerk: vanaf (€)');
        $f('sc_max', 'Schilderwerk: tot (€)');
        echo '</div><p><label>Persoonlijke toelichting in de mail (optioneel)<br><textarea form="ow-quote-form" name="ow_quote[note]" rows="3" class="large-text"></textarea></label></p>';
        echo '<p><button type="submit" form="ow-quote-form" class="button button-primary">' . ($status === Storage::STATUS_SENT ? 'Offerte opnieuw versturen' : 'Offerte versturen naar klant') . '</button> ';
        echo '<button type="submit" form="ow-quote-form" formaction="' . esc_url(admin_url('admin-post.php?action=ow_mark_done')) . '" class="button">Markeer als zelf afgehandeld</button></p>';
        echo '<p class="description">Wil je niets versturen? Laat deze aanvraag dan gewoon staan en neem zelf contact op: er gaat dan niets naar de klant.</p>';
    }

    /** Formulier buiten het bericht-formulier (geneste formulieren kunnen niet); de velden hierboven horen er via form="" bij. */
    public static function footerForm(): void
    {
        $post = get_post();
        if (!$post || $post->post_type !== Storage::POST_TYPE) {
            return;
        }
        echo '<form id="ow-quote-form" method="post" action="' . esc_url(admin_url('admin-post.php?action=ow_send_quote')) . '">';
        wp_nonce_field('ow_inbox_' . $post->ID);
        echo '<input type="hidden" name="post_id" value="' . (int) $post->ID . '"></form>';
    }

    public static function adminSend(): void
    {
        $id = (int) ($_POST['post_id'] ?? 0);
        self::authorize($id);
        $custom = isset($_POST['ow_quote']) && is_array($_POST['ow_quote']) ? wp_unslash($_POST['ow_quote']) : [];
        [$ok, $msg] = QuoteSender::send($id, $custom);
        self::back($id, $ok, $msg);
    }

    public static function adminDone(): void
    {
        $id = (int) ($_POST['post_id'] ?? 0);
        self::authorize($id);
        Storage::setStatus($id, Storage::STATUS_DONE);
        self::back($id, true, 'Gemarkeerd als zelf afgehandeld. Er is niets naar de klant gestuurd.');
    }

    private static function authorize(int $id): void
    {
        if ($id <= 0 || get_post_type($id) !== Storage::POST_TYPE || !current_user_can('edit_post', $id)) {
            wp_die('Geen toegang.');
        }
        check_admin_referer('ow_inbox_' . $id);
    }

    private static function back(int $id, bool $ok, string $msg): void
    {
        wp_safe_redirect(add_query_arg(['ow_ok' => $ok ? '1' : '0', 'ow_msg' => rawurlencode($msg)], admin_url('post.php?post=' . $id . '&action=edit')));
        exit;
    }

    public static function notice(): void
    {
        if (!isset($_GET['ow_msg']) || !current_user_can('edit_posts')) {
            return;
        }
        $ok = ($_GET['ow_ok'] ?? '') === '1';
        echo '<div class="notice notice-' . ($ok ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html(rawurldecode((string) wp_unslash($_GET['ow_msg']))) . '</p></div>';
    }

    public static function columns(array $cols): array
    {
        return [
            'cb' => $cols['cb'] ?? '',
            'title' => 'Klant',
            'ow_status' => 'Status',
            'ow_email' => 'E-mail',
            'date' => $cols['date'] ?? 'Datum',
        ];
    }

    public static function column(string $col, int $id): void
    {
        if ($col === 'ow_status') {
            $labels = [Storage::STATUS_NEW => 'Nieuw', Storage::STATUS_SENT => 'Offerte verstuurd', Storage::STATUS_DONE => 'Zelf afgehandeld'];
            echo esc_html($labels[Storage::status($id)]);
        } elseif ($col === 'ow_email') {
            $d = Storage::data($id);
            echo esc_html($d['email'] ?? '');
        }
    }
}
