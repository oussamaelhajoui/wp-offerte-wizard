<?php
namespace OfferteWizard;

final class Admin
{
    const GROUP = 'ow_group';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'init']);
        add_action('admin_post_ow_create_page', [self::class, 'createPage']);
    }

    public static function menu(): void
    {
        add_options_page('Offerte Wizard', 'Offerte Wizard', 'manage_options', 'offerte-wizard', [self::class, 'render']);
    }

    public static function init(): void
    {
        register_setting(self::GROUP, Settings::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => Settings::defaults(),
        ]);
    }

    /** @param mixed $in */
    public static function sanitize($in): array
    {
        delete_transient(Updater::TRANSIENT);
        return Settings::sanitize(is_array($in) ? wp_unslash($in) : [], Settings::get());
    }

    public static function createPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die('Geen toegang.');
        }
        check_admin_referer('ow_create_page');
        $id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => 'Offerte aanvragen (wizard)',
            'post_content' => '[offerte_wizard fullpage="1"]',
        ]);
        wp_safe_redirect($id && !is_wp_error($id) ? get_edit_post_link($id, 'raw') : admin_url('options-general.php?page=offerte-wizard'));
        exit;
    }

    private static function fields(): array
    {
        return [
            'Aanvragen' => [
                ['recipient', 'E-mailadres voor nieuwe aanvragen', 'email', 'Leeg = het beheerders-e-mailadres van de site.'],
                ['brand_name', 'Bedrijfsnaam (in mails aan de klant)', 'text', ''],
            ],
            '1. Eindscherm: wat ziet de klant na het versturen?' => [
                ['show_price', 'Eindscherm', 'select', 'Jij krijgt de berekende prijs altijd in je eigen mail, welke keuze je ook maakt.', [
                    '1' => 'Toon de prijsrange (met toelichting en eventueel een eigen tekst)',
                    '0' => 'Toon geen prijs, maar een bericht dat we contact opnemen',
                ]],
                ['price_disclaimer', 'Toelichting bij de prijsrange', 'textarea', 'Staat onder de prijs. Alleen zichtbaar als je de prijsrange toont (en onderaan de offertemail met de berekende prijs).'],
                ['shown_price_extra', 'Eigen extra tekst bij de prijs (optioneel)', 'textarea', 'Verschijnt in een opvallende kaart onder de prijs. Leeg = niet tonen. Bijvoorbeeld een actie of een opmerking over steigerwerk.'],
                ['hidden_price_title', 'Titel van het bericht (zonder prijs)', 'text', 'Alleen zichtbaar als je kiest voor "geen prijs, wel een bericht".'],
                ['hidden_price_message', 'Tekst van het bericht (zonder prijs)', 'textarea', 'Staat in een opvallende kaart in plaats van de prijs, en onderaan de ontvangstbevestiging (als die aan staat en geen prijs bevat).'],
            ],
            '2. Mail naar de klant' => [
                ['customer_mail', 'Mail naar de klant', 'select', '', [
                    'uit' => 'Geen mail naar de klant: ik neem zelf contact op',
                    'aan' => 'Stuur de klant een ontvangstbevestiging',
                ]],
                ['mail_price', 'Prijs in die mail', 'select', 'Los van het eindscherm: je kunt de prijs op het scherm verbergen maar wel mailen, of andersom. Met prijs krijgt de klant meteen de offerte en staat de aanvraag als "verstuurd" bij je. Is er geen prijs berekend, dan krijgt de klant de bevestiging zonder prijs.', [
                    '0' => 'Zonder prijsindicatie (jij beslist later per aanvraag)',
                    '1' => 'Met de berekende prijsindicatie',
                ]],
                ['quote_intro', 'Openingstekst als de mail een prijs bevat', 'textarea', 'Staat bovenaan de mail als de klant de offerte krijgt, ook als jij later een offerte stuurt.'],
            ],
            '3. Knoppen in de mail aan jou' => [
                ['owner_link_send', 'Knop "Offerte sturen naar klant"', 'checkbox', 'Eén klik (met een bevestigingspagina) en de klant krijgt de offerte met de berekende prijsindicatie.'],
                ['owner_link_admin', 'Knop "Aanvraag openen"', 'checkbox', 'Opent de aanvraag in wp-admin: daar vul je een eigen prijs in en verstuur je, of markeer je hem als zelf afgehandeld.'],
            ],
            'Bouwplan uitlezen' => [
                ['mode', 'Standaardmodus', 'select', 'Per snippet te overschrijven met mode="ai|tekst|handmatig".', [
                    'auto' => 'Automatisch: AI als er een key is, anders tekst, anders handmatig',
                    'ai' => 'Alleen AI',
                    'tekst' => 'Alleen tekstextractie (PDF met maatvoering, geen externe dienst)',
                    'handmatig' => 'Handmatig: bouwplan alleen als bijlage',
                ]],
                ['ai_provider', 'AI-provider', 'select', '', ['claude' => 'Claude (Anthropic)', 'openai' => 'OpenAI (ChatGPT)']],
                ['ai_model', 'AI-model', 'text', 'Bijvoorbeeld claude-sonnet-5-5 of gpt-4o.'],
                ['ai_key', 'API-key', 'password', 'Leeg laten = ongewijzigd. De key wordt nooit op de website getoond.'],
                ['ai_key_clear', 'API-key verwijderen', 'checkbox', ''],
                ['ceiling_height', 'Plafondhoogte voor wandberekening (m)', 'text', 'Wanden = omtrek × deze hoogte.'],
                ['max_upload_mb', 'Maximale uploadgrootte (MB)', 'number', ''],
                ['retention_days', 'Bouwplannen bewaren (dagen)', 'number', 'Daarna worden de bestanden van de server verwijderd.'],
            ],
            'Prijsindicatie (per m², incl. arbeid, materiaal en BTW)' => [
                ['price_stuc_wand_min', 'Stucwerk wanden: vanaf (€)', 'text', ''],
                ['price_stuc_wand_max', 'Stucwerk wanden: tot (€)', 'text', ''],
                ['price_stuc_plafond_min', 'Stucwerk plafonds: vanaf (€)', 'text', ''],
                ['price_stuc_plafond_max', 'Stucwerk plafonds: tot (€)', 'text', ''],
                ['price_schilder_min', 'Schilderwerk: vanaf (€)', 'text', ''],
                ['price_schilder_max', 'Schilderwerk: tot (€)', 'text', ''],
            ],
            'Updates' => [
                ['update_url', 'Update-URL', 'text', 'Link naar het bestand offerte-wizard.json van de nieuwste versie; standaard de GitHub-releases van deze plugin. Leeg = geen automatische updates (de zip handmatig uploaden kan altijd).'],
            ],
            'Uiterlijk' => [
                ['logo_url', 'Logo (URL of pad vanaf de domeinroot)', 'text', ''],
                ['color_accent', 'Accentkleur', 'color', ''],
                ['color_dark', 'Donkere kleur (tekst en knoppen)', 'color', ''],
                ['color_soft', 'Lichte vlakkleur', 'color', ''],
                ['load_everywhere', 'Laad de wizard op alle pagina\'s', 'checkbox', 'Alleen nodig als je de wizard via een thema-template plaatst; in een pagina of module detecteert de plugin hem zelf.'],
            ],
        ];
    }

    private static function updateBox(array $s): void
    {
        echo '<h2>Versie en updates</h2><p>Geïnstalleerde versie: <strong>' . esc_html(OW_VERSION) . '</strong>. ';
        if ($s['update_url'] === '') {
            echo 'Automatische updates staan uit: vul hieronder bij "Updates" de Update-URL in. Je kunt ook altijd de nieuwe zip uploaden via Plugins → Nieuwe plugin → Plugin uploaden.</p>';
            return;
        }
        $info = Updater::remote();
        if ($info === null) {
            echo 'De update-URL is niet bereikbaar of ongeldig.</p>';
        } elseif (UpdateInfo::isNewer(OW_VERSION, $info)) {
            echo 'Nieuwste versie: <strong>' . esc_html($info['version']) . '</strong>. <a href="' . esc_url(admin_url('plugins.php')) . '">Ga naar Plugins om bij te werken</a>.</p>';
        } else {
            echo 'Je hebt de nieuwste versie.</p>';
        }
        if (isset($_GET['ow_upd'])) {
            $msg = ['nieuw' => 'Er is een nieuwe versie beschikbaar.', 'actueel' => 'Je hebt de nieuwste versie.', 'fout' => 'De update-URL is niet bereikbaar of ongeldig.'][(string) $_GET['ow_upd']] ?? '';
            if ($msg !== '') {
                echo '<div class="notice notice-info inline"><p>' . esc_html($msg) . '</p></div>';
            }
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:8px 0 16px">';
        wp_nonce_field('ow_check_update');
        echo '<input type="hidden" name="action" value="ow_check_update">';
        submit_button('Nu op updates controleren', 'secondary', 'submit', false);
        echo '</form>';
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = Settings::get();
        echo '<div class="wrap"><h1>Offerte Wizard</h1>';
        echo '<h2>Plaatsen</h2><p>Zet een van deze regels in een Tekst- of Code-module (in een column) of op een pagina:</p><ul style="list-style:disc;margin-left:20px">';
        echo '<li><code>[offerte_wizard]</code> – in een column of sectie</li>';
        echo '<li><code>[offerte_wizard fullpage="1"]</code> – schermvullend, over de pagina heen</li>';
        echo '<li><code>[offerte_wizard mode="handmatig" bouwplan="uit"]</code> – opties per plaatsing</li>';
        echo '<li><code>&lt;offerte-wizard data-offerte-wizard style="display:block"&gt;&lt;/offerte-wizard&gt;</code> – zonder shortcode, voor een Code-module</li></ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0">';
        wp_nonce_field('ow_create_page');
        echo '<input type="hidden" name="action" value="ow_create_page">';
        submit_button('Maak een conceptpagina met de wizard', 'secondary', 'submit', false);
        echo '</form>';

        self::updateBox($s);

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        foreach (self::fields() as $section => $rows) {
            echo '<h2>' . esc_html($section) . '</h2><table class="form-table" role="presentation">';
            foreach ($rows as $f) {
                [$key, $label, $type, $help] = $f;
                $name = Settings::OPTION . '[' . $key . ']';
                $val = $s[$key] ?? '';
                echo '<tr><th scope="row"><label for="ow_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
                if ($type === 'checkbox') {
                    echo '<input type="checkbox" id="ow_' . esc_attr($key) . '" name="' . esc_attr($name) . '" value="1"' . checked(!empty($val), true, false) . '>';
                } elseif ($type === 'select') {
                    echo '<select id="ow_' . esc_attr($key) . '" name="' . esc_attr($name) . '">';
                    foreach ($f[4] as $v => $l) {
                        echo '<option value="' . esc_attr($v) . '"' . selected($val, $v, false) . '>' . esc_html($l) . '</option>';
                    }
                    echo '</select>';
                } elseif ($type === 'textarea') {
                    echo '<textarea class="large-text" rows="3" id="ow_' . esc_attr($key) . '" name="' . esc_attr($name) . '">' . esc_textarea((string) $val) . '</textarea>';
                } elseif ($type === 'password') {
                    echo '<input type="password" autocomplete="new-password" class="regular-text" id="ow_' . esc_attr($key) . '" name="' . esc_attr($name) . '" value="" placeholder="' . ($val !== '' ? '•••••••• (ingesteld)' : '') . '">';
                } else {
                    echo '<input type="' . esc_attr($type) . '" class="regular-text" id="ow_' . esc_attr($key) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $val) . '">';
                }
                if ($help !== '') {
                    echo '<p class="description">' . esc_html($help) . '</p>';
                }
                echo '</td></tr>';
            }
            echo '</table>';
        }
        submit_button();
        echo '</form></div>';
    }
}
