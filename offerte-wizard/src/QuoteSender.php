<?php
namespace OfferteWizard;

/** Mailt de offerte naar de klant (met de berekende of een eigen prijs) en onthoudt dat dit gebeurd is. */
final class QuoteSender
{
    /**
     * @param array $custom st_min, st_max, sc_min, sc_max, note; leeg = berekende indicatie
     * @return array{0:bool,1:string} [gelukt, melding]
     */
    public static function send(int $id, array $custom): array
    {
        $data = Storage::data($id);
        if ($data === null) {
            return [false, 'Aanvraag niet gevonden.'];
        }
        $prices = QuotePrices::resolve($data['pricing'], $custom);
        if (!$prices['stucwerk'] && !$prices['schilderwerk']) {
            return [false, 'Er is geen prijs bekend voor deze aanvraag. Vul een eigen prijs in.'];
        }
        $s = Settings::get();
        // De richtprijs-toelichting hoort bij de berekende indicatie, niet bij een prijs die jij zelf invulde.
        $ownPrice = false;
        foreach (['st_min', 'st_max', 'sc_min', 'sc_max'] as $k) {
            if (isset($custom[$k]) && is_scalar($custom[$k]) && trim((string) $custom[$k]) !== '') {
                $ownPrice = true;
            }
        }
        $mail = Mailer::composeQuote($data, $prices, (string) $s['brand_name'], (string) $s['quote_intro'], $ownPrice ? '' : (string) $s['price_disclaimer']);
        $owner = $s['recipient'] !== '' ? $s['recipient'] : (string) get_option('admin_email');
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $owner];
        if (!wp_mail($data['email'], $mail['subject'], $mail['body'], $headers)) {
            return [false, 'De mail kon niet worden verzonden. Controleer de mailinstellingen van de site.'];
        }
        update_post_meta($id, '_ow_quote', wp_slash(wp_json_encode($prices)));
        update_post_meta($id, '_ow_sent_at', time());
        Storage::setStatus($id, Storage::STATUS_SENT);
        return [true, 'Offerte verstuurd naar ' . $data['email'] . '.'];
    }
}
