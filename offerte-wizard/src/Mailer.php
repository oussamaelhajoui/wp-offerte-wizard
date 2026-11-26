<?php
namespace OfferteWizard;

/** Stelt de mails samen (platte tekst). Verzenden gebeurt in Submission/Rest. */
final class Mailer
{
    /**
     * Mail aan de eigenaar.
     * @param array{send?:string,admin?:string} $links knoppen (links) onderaan de mail
     * @return array{subject:string,body:string}
     */
    public static function compose(array $d, array $links = [], bool $priceShown = true): array
    {
        $name = trim($d['first_name'] . ' ' . $d['last_name']);
        $subject = self::oneLine('Nieuwe offerte-aanvraag: ' . $name . ' (' . $d['locality'] . ')');

        $L = [];
        $L[] = 'NIEUWE OFFERTE-AANVRAAG';
        $L[] = '';
        $L[] = 'Klant';
        $L[] = '  Naam: ' . $name;
        $L[] = '  E-mail: ' . $d['email'];
        $L[] = '  Telefoon: ' . $d['phone_number'];
        $L[] = '  Adres klus: ' . $d['address_line'] . ', ' . $d['post_code'] . ' ' . $d['locality'];
        $L[] = '';
        $L[] = 'Klus';
        $L[] = '  Type: ' . ($d['type_woning'] === 'nieuwbouw' ? 'Nieuwbouw' : 'Renovatie');
        $L[] = '  Werkzaamheden: ' . ($d['schilderen'] ? 'Stucwerk en schilderwerk' : 'Stucwerk');
        $L[] = '  Gewenste periode: ' . self::period($d['start'], $d['eind']);
        $L[] = '  Weet m²?: ' . ['ja' => 'Ja, ongeveer', 'nee' => 'Nee, hulp gewenst', 'bouwplan' => 'Bouwplan geüpload'][$d['m2_keuze']];
        if ($d['wand'] !== null) {
            $L[] = '  m² wanden: ' . self::n($d['wand']);
        }
        if ($d['plafond'] !== null) {
            $L[] = '  m² plafonds: ' . self::n($d['plafond']);
        }
        if ($d['daktype'] !== '') {
            $L[] = '  Daktype: ' . ['punt' => 'Punt dak', 'plat' => 'Plat dak', 'appartement' => 'Appartement'][$d['daktype']];
        }
        if ($d['ondergrond']) {
            $labels = ['baksteen' => 'Baksteen of Gevel', 'gips' => 'Gipsplaten of Korrels', 'beton' => 'Beton'];
            $L[] = '  Ondergrond: ' . implode(', ', array_map(static function ($o) use ($labels) {
                return $labels[$o];
            }, $d['ondergrond']));
        }
        if ($d['rooms']) {
            $L[] = '';
            $L[] = 'Ruimtes uit bouwplan';
            foreach ($d['rooms'] as $r) {
                $L[] = '  - ' . ($r['name'] !== '' ? $r['name'] : 'Ruimte') . ': plafond ' . self::n($r['floor_m2']) . ' m², wanden ' . self::n($r['wall_m2']) . ' m²';
            }
        }
        if ($d['m2_keuze'] === 'bouwplan') {
            $L[] = '';
            $L[] = $d['plan_token'] !== '' ? 'Bouwplan: zie bijlage.' : 'Bouwplan: geen bijlage beschikbaar.';
        }
        $p = $d['pricing'];
        $L[] = '';
        $L[] = $priceShown
            ? 'Getoonde prijsindicatie (incl. arbeid, materiaal en BTW)'
            : 'Berekende prijsindicatie, NIET getoond aan de klant (incl. arbeid, materiaal en BTW)';
        if ($p === null) {
            $L[] = '  Geen indicatie beschikbaar (m² onbekend).';
        } else {
            if ($p['stucwerk']) {
                $L[] = '  Stucwerk: ' . self::euro($p['stucwerk']['min']) . ' - ' . self::euro($p['stucwerk']['max']);
            }
            if ($p['schilderwerk']) {
                $L[] = '  Schilderwerk: ' . self::euro($p['schilderwerk']['min']) . ' - ' . self::euro($p['schilderwerk']['max']);
            }
            if ($p['dagen']) {
                $L[] = '  Verwachte klusdagen: ' . $p['dagen']['min'] . '-' . $p['dagen']['max'];
            }
        }
        if ($d['beschrijving'] !== '') {
            $L[] = '';
            $L[] = 'Opmerkingen van de klant';
            $L[] = $d['beschrijving'];
        }
        if (!empty($links['send']) || !empty($links['admin'])) {
            $L[] = '';
            $L[] = 'WAT WIL JE MET DEZE AANVRAAG?';
            if (!empty($links['send'])) {
                $L[] = '  Offerte sturen naar klant (met bovenstaande prijsindicatie):';
                $L[] = '  ' . $links['send'];
            }
            if (!empty($links['admin'])) {
                $L[] = '  Aanvraag openen, eigen prijs invullen en versturen (of zelf afhandelen):';
                $L[] = '  ' . $links['admin'];
            }
            $L[] = '  Niets doen? Dan wordt er niets naar de klant gestuurd en neem je zelf contact op.';
        }
        return ['subject' => $subject, 'body' => implode("\n", $L) . "\n"];
    }

    /** Ontvangstbevestiging aan de klant, zonder prijs. @return array{subject:string,body:string} */
    public static function composeConfirmation(array $d, string $brand): array
    {
        $L = [
            'Hallo ' . $d['first_name'] . ',',
            '',
            'Bedankt voor je aanvraag bij ' . $brand . '. We hebben je gegevens ontvangen en nemen zo snel mogelijk contact met je op om je klus samen door te nemen en een offerte op te maken.',
            '',
            'Je aanvraag in het kort:',
            '  Periode: ' . self::period($d['start'], $d['eind']),
            '  Locatie: ' . $d['address_line'] . ', ' . $d['post_code'] . ' ' . $d['locality'],
            '',
            'Met vriendelijke groet,',
            $brand,
        ];
        return ['subject' => self::oneLine('Je offerte-aanvraag bij ' . $brand), 'body' => implode("\n", $L) . "\n"];
    }

    /**
     * De offerte zelf, naar de klant.
     * @param array{stucwerk:?array,schilderwerk:?array,note:string} $prices uitkomst van QuotePrices::resolve
     * @return array{subject:string,body:string}
     */
    public static function composeQuote(array $d, array $prices, string $brand, string $intro): array
    {
        $L = ['Hallo ' . $d['first_name'] . ',', '', trim($intro), '', 'Je offerte (inclusief arbeid, materiaal en BTW):'];
        if ($prices['stucwerk']) {
            $L[] = '  Stucwerk: ' . self::range($prices['stucwerk']);
        }
        if ($prices['schilderwerk']) {
            $L[] = '  Schilderwerk: ' . self::range($prices['schilderwerk']);
        }
        if ($prices['note'] !== '') {
            $L[] = '';
            $L[] = $prices['note'];
        }
        $L[] = '';
        $L[] = 'Je aanvraag';
        $L[] = '  Periode: ' . self::period($d['start'], $d['eind']);
        $L[] = '  Locatie: ' . $d['address_line'] . ', ' . $d['post_code'] . ' ' . $d['locality'];
        $L[] = '';
        $L[] = 'Kloppen de gegevens niet of heb je vragen? Reageer gerust op deze mail.';
        $L[] = '';
        $L[] = 'Met vriendelijke groet,';
        $L[] = $brand;
        return ['subject' => self::oneLine('Je offerte van ' . $brand), 'body' => implode("\n", $L) . "\n"];
    }

    /** @param array{min:int,max:int} $r */
    private static function range(array $r): string
    {
        return $r['min'] === $r['max'] ? self::euro($r['min']) : self::euro($r['min']) . ' - ' . self::euro($r['max']);
    }

    private static function oneLine(string $s): string
    {
        return trim(preg_replace('/[\r\n\t]+/', ' ', $s));
    }

    /** "26-11-2026" voor één dag, anders "26-11-2026 t/m 29-11-2026". */
    private static function period(string $start, string $end): string
    {
        return $start === $end ? self::date($start) : self::date($start) . ' t/m ' . self::date($end);
    }

    private static function date(string $ymd): string
    {
        [$y, $m, $dd] = explode('-', $ymd);
        return $dd . '-' . $m . '-' . $y;
    }

    private static function n($v): string
    {
        return $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    }

    private static function euro(int $v): string
    {
        return '€' . number_format($v, 0, ',', '.');
    }
}
