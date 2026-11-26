<?php
namespace OfferteWizard;

final class Settings
{
    const OPTION = 'ow_settings';
    const MODES = ['auto', 'ai', 'tekst', 'handmatig'];
    const DEFAULT_UPDATE_URL = 'https://github.com/oussamaelhajoui/wp-offerte-wizard/releases/latest/download/offerte-wizard.json';
    const PROVIDERS = ['claude', 'openai'];
    /** uit = geen mail naar de klant; aan = ontvangstbevestiging (met of zonder prijs, zie mail_price) */
    const CUSTOMER_MAIL = ['uit', 'aan'];
    const PRICE_KEYS = [
        'price_stuc_wand_min', 'price_stuc_wand_max',
        'price_stuc_plafond_min', 'price_stuc_plafond_max',
        'price_schilder_min', 'price_schilder_max',
    ];
    const DEFAULT_MODELS = ['claude' => 'claude-sonnet-5-5', 'openai' => 'gpt-4o'];

    public static function defaults(): array
    {
        return [
            'recipient' => '',
            'brand_name' => 'Afbouw Megens',
            // 1. Eindscherm: de prijsrange tonen (met toelichting en eigen extra tekst) of alleen een contact-bericht.
            //    Standaard geen prijs: jij beslist per aanvraag of en wat de klant krijgt.
            'show_price' => 0,
            'price_disclaimer' => 'Dit is een richtprijs op basis van de ingevulde informatie, inclusief arbeid en materiaal, zodat je achteraf niet voor verrassingen komt te staan. We nemen contact met je op voor de definitieve offerte.',
            'shown_price_extra' => '',
            'hidden_price_title' => 'Bedankt, we nemen contact met je op',
            'hidden_price_message' => 'We bekijken je aanvraag persoonlijk en nemen zo snel mogelijk contact met je op om je klus en de prijs door te nemen.',
            // 2. Mail naar de klant: wel of niet, en met of zonder prijs erin.
            'customer_mail' => 'aan',
            'mail_price' => 0,
            'quote_intro' => 'Bedankt voor je aanvraag. Hieronder vind je onze offerte.',
            // 3. Knoppen in de mail aan de eigenaar.
            'owner_link_send' => 1,
            'owner_link_admin' => 1,
            'price_stuc_wand_min' => 16.0,
            'price_stuc_wand_max' => 22.0,
            'price_stuc_plafond_min' => 14.0,
            'price_stuc_plafond_max' => 20.0,
            'price_schilder_min' => 17.57,
            'price_schilder_max' => 20.5,
            'ceiling_height' => 2.6,
            'mode' => 'auto',
            'ai_provider' => 'claude',
            'ai_model' => self::DEFAULT_MODELS['claude'],
            'ai_key' => '',
            'max_upload_mb' => 15,
            'retention_days' => 90,
            'logo_url' => '/wp-content/uploads/2026/08/LOGO-AFBOUW-MEGENS@0.5x.webp',
            'color_accent' => '#c39455',
            'color_dark' => '#07172f',
            'color_soft' => '#eef5fb',
            'load_everywhere' => 0,
            'update_url' => self::DEFAULT_UPDATE_URL,
        ];
    }

    /** Huidige instellingen (vereist WordPress). */
    public static function get(): array
    {
        $saved = function_exists('get_option') ? get_option(self::OPTION, []) : [];
        return self::migrate(array_merge(self::defaults(), is_array($saved) ? $saved : []));
    }

    /** Eerdere standaardteksten (1.6.0, 1.6.1): wie ze nooit aanpaste krijgt de nieuwe standaard. */
    const LEGACY_HIDDEN_MESSAGES = [
        'Je prijsindicatie ontvang je van ons zodra we je aanvraag hebben bekeken.',
        'We bekijken je aanvraag persoonlijk en laten je zo snel mogelijk weten wat de klus kost. Je hoort van ons.',
    ];
    const LEGACY_HIDDEN_TITLE = 'Je prijsindicatie is onderweg';
    /** @deprecated alias voor tests/oude code */
    const LEGACY_HIDDEN_MESSAGE = self::LEGACY_HIDDEN_MESSAGES[0];

    public static function migrate(array $s): array
    {
        $d = self::defaults();
        if (in_array($s['hidden_price_message'] ?? '', self::LEGACY_HIDDEN_MESSAGES, true)) {
            $s['hidden_price_message'] = $d['hidden_price_message'];
        }
        if (($s['hidden_price_title'] ?? '') === self::LEGACY_HIDDEN_TITLE) {
            $s['hidden_price_title'] = $d['hidden_price_title'];
        }
        // 1.2-1.6: "ontvangst" (zonder prijs) en "direct" (met prijs) waren één keuzelijst.
        $cm = $s['customer_mail'] ?? 'aan';
        if ($cm === 'direct') {
            $s['mail_price'] = 1;
        }
        if ($cm === 'ontvangst' || $cm === 'direct') {
            $s['customer_mail'] = 'aan';
        }
        return $s;
    }

    /** Schoont formulierinvoer op; ongeldige waarden vallen terug op de oude. */
    public static function sanitize(array $in, array $old): array
    {
        $d = self::migrate(array_merge(self::defaults(), $old));
        $out = $d;

        $email = trim((string) ($in['recipient'] ?? ''));
        $out['recipient'] = ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : $d['recipient'];
        $out['load_everywhere'] = empty($in['load_everywhere']) ? 0 : 1;
        $out['show_price'] = empty($in['show_price']) ? 0 : 1;
        $out['owner_link_send'] = empty($in['owner_link_send']) ? 0 : 1;
        $out['owner_link_admin'] = empty($in['owner_link_admin']) ? 0 : 1;
        $in = self::migrate($in + ['customer_mail' => $d['customer_mail']]);
        $cm = (string) ($in['customer_mail'] ?? '');
        $out['customer_mail'] = in_array($cm, self::CUSTOMER_MAIL, true) ? $cm : $d['customer_mail'];
        $out['mail_price'] = empty($in['mail_price']) ? 0 : 1;
        $pd = trim(strip_tags(str_replace("\r\n", "\n", (string) ($in['price_disclaimer'] ?? ''))));
        $out['price_disclaimer'] = $pd !== '' ? mb_substr($pd, 0, 600) : $d['price_disclaimer'];
        // Extra tekst bij de prijs is optioneel: leeg mag.
        $out['shown_price_extra'] = mb_substr(trim(strip_tags(str_replace("\r\n", "\n", (string) ($in['shown_price_extra'] ?? '')))), 0, 500);
        $intro = trim(strip_tags(str_replace("\r\n", "\n", (string) ($in['quote_intro'] ?? ''))));
        $out['quote_intro'] = $intro !== '' ? mb_substr($intro, 0, 1000) : $d['quote_intro'];
        $hm = trim(strip_tags(str_replace("\r\n", "\n", (string) ($in['hidden_price_message'] ?? ''))));
        $out['hidden_price_message'] = $hm !== '' ? mb_substr($hm, 0, 500) : $d['hidden_price_message'];
        $ht = self::line($in['hidden_price_title'] ?? '', 80);
        $out['hidden_price_title'] = $ht !== '' ? $ht : $d['hidden_price_title'];

        $brand = self::line($in['brand_name'] ?? '', 60);
        $out['brand_name'] = $brand !== '' ? $brand : $d['brand_name'];

        foreach (self::PRICE_KEYS as $k) {
            $v = array_key_exists($k, $in) ? NumberParser::parse((string) $in[$k]) : null;
            $out[$k] = ($v !== null && $v <= 1000) ? $v : (float) $d[$k];
        }
        foreach (['stuc_wand', 'stuc_plafond', 'schilder'] as $p) {
            $min = 'price_' . $p . '_min';
            $max = 'price_' . $p . '_max';
            if ($out[$min] > $out[$max]) {
                $tmp = $out[$min];
                $out[$min] = $out[$max];
                $out[$max] = $tmp;
            }
        }

        $h = array_key_exists('ceiling_height', $in) ? NumberParser::parse((string) $in['ceiling_height']) : null;
        $out['ceiling_height'] = ($h !== null && $h >= 2.0 && $h <= 4.0) ? $h : (float) $d['ceiling_height'];

        $mode = (string) ($in['mode'] ?? '');
        $out['mode'] = in_array($mode, self::MODES, true) ? $mode : $d['mode'];
        $prov = (string) ($in['ai_provider'] ?? '');
        $out['ai_provider'] = in_array($prov, self::PROVIDERS, true) ? $prov : $d['ai_provider'];
        $model = self::line($in['ai_model'] ?? '', 80);
        $out['ai_model'] = $model !== '' ? $model : self::DEFAULT_MODELS[$out['ai_provider']];

        if (!empty($in['ai_key_clear'])) {
            $out['ai_key'] = '';
        } else {
            $key = trim((string) ($in['ai_key'] ?? ''));
            $out['ai_key'] = $key !== '' ? $key : $d['ai_key'];
        }

        $upd = trim((string) ($in['update_url'] ?? ''));
        $out['update_url'] = ($upd === '' || UpdateInfo::urlAllowed($upd)) ? $upd : $d['update_url'];

        $out['max_upload_mb'] = self::intRange($in['max_upload_mb'] ?? null, 1, 25, (int) $d['max_upload_mb']);
        $out['retention_days'] = self::intRange($in['retention_days'] ?? null, 1, 365, (int) $d['retention_days']);

        $logo = trim((string) ($in['logo_url'] ?? ''));
        if ($logo === '' || preg_match('#^(/|https?://)[^\s"\'<>]+$#', $logo)) {
            $out['logo_url'] = $logo;
        } else {
            $out['logo_url'] = $d['logo_url'];
        }
        foreach (['color_accent', 'color_dark', 'color_soft'] as $c) {
            $v = trim((string) ($in[$c] ?? ''));
            $out[$c] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $d[$c];
        }
        return $out;
    }

    private static function line($v, int $max): string
    {
        $s = trim(preg_replace('/\s+/', ' ', strip_tags((string) $v)));
        return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
    }

    private static function intRange($v, int $min, int $max, int $fallback): int
    {
        if (!is_numeric($v)) {
            return $fallback;
        }
        $i = (int) $v;
        return ($i >= $min && $i <= $max) ? $i : $fallback;
    }
}
