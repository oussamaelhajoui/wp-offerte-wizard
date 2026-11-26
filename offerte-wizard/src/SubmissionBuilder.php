<?php
namespace OfferteWizard;

/**
 * Loopt de antwoorden van de klant opnieuw door de hele flow (de browser is
 * niet te vertrouwen) en maakt er een schone aanvraag van.
 */
final class SubmissionBuilder
{
    const MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

    /** @return array{errors:array<string,string>,data:array} */
    public static function build(array $answers, string $today, array $settings): array
    {
        $id = 'welkom';
        $guard = 0;
        while ($id !== null && $id !== 'overzicht' && $guard++ < 30) {
            $err = Flow::validate($id, $answers, $today);
            if ($err !== null) {
                return ['errors' => [$id => $err], 'data' => []];
            }
            $id = Flow::next($id, $answers);
        }

        $m2 = (string) $answers['m2'];
        $wand = NumberParser::parse(self::scalar($answers['m2_wand'] ?? ''));
        $plafond = NumberParser::parse(self::scalar($answers['m2_plafond'] ?? ''));
        $need = Flow::surfaces($answers);
        if ($m2 === 'nee') {
            $wand = $plafond = null;
        }
        // Een oppervlak dat niet gestuct hoeft te worden telt nergens mee (ook niet na een bouwplan met beide maten).
        if (!$need['wand']) {
            $wand = null;
        }
        if (!$need['plafond']) {
            $plafond = null;
        }
        $schilderen = (string) $answers['schilderen'] === 'ja';
        // Alleen een prijs als alle te stucen oppervlakken bekend zijn: een prijs over maar een deel zou te laag zijn.
        $complete = (!$need['wand'] || $wand !== null) && (!$need['plafond'] || $plafond !== null);
        $pricing = ($complete && ($wand !== null || $plafond !== null))
            ? Pricing::compute((float) $wand, (float) $plafond, $schilderen, $settings)
            : null;

        $token = self::scalar($answers['plan_token'] ?? '');
        $data = [
            'type_woning' => (string) $answers['type_woning'],
            'onderdelen' => (string) $answers['onderdelen'],
            'm2_keuze' => $m2,
            'wand' => $wand,
            'plafond' => $plafond,
            'daktype' => ($m2 === 'nee' || !$need['plafond']) ? '' : (string) ($answers['daktype'] ?? ''),
            'ondergrond' => $m2 === 'nee' ? array_values(array_intersect((array) $answers['ondergrond'], Flow::ONDERGROND)) : [],
            'schilderen' => $schilderen,
            'periode_keuze' => (string) $answers['periode_keuze'],
            'start' => (string) $answers['periode_keuze'] === 'weet' ? (string) $answers['periode_start'] : '',
            'eind' => (string) $answers['periode_keuze'] === 'weet' ? (string) $answers['periode_eind'] : '',
            'first_name' => self::line($answers['first_name'], 60),
            'last_name' => self::line($answers['last_name'], 80),
            'email' => trim((string) $answers['email']),
            'phone_number' => self::line($answers['phone_number'], 30),
            'address_line' => self::line($answers['address_line'], 120),
            'locality' => self::line($answers['locality'], 80),
            'post_code' => strtoupper(self::line($answers['post_code'], 10)),
            'beschrijving' => self::text($answers['beschrijving'] ?? '', 2000),
            'plan_token' => ($m2 === 'bouwplan' && preg_match('/^[a-f0-9]{32}$/', $token)) ? $token : '',
            'rooms' => $m2 === 'bouwplan' ? self::rooms($answers['rooms'] ?? []) : [],
            'pricing' => $pricing,
        ];
        return ['errors' => [], 'data' => $data];
    }

    /** Wat de klant na het versturen te zien krijgt. */
    public static function summary(array $d, bool $showPrice = true, string $hiddenMessage = ''): array
    {
        $p = $d['pricing'];
        $hidden = !$showPrice && $p !== null;
        if (!$showPrice) {
            $p = null;
        }
        $werk = 'Stucwerk (' . self::surfaceText($d) . ')' . ($d['schilderen'] ? ' en Schilderwerk' : '');
        $total = ($d['wand'] ?? 0) + ($d['plafond'] ?? 0);
        return [
            'periode' => self::periodText($d),
            'type' => $d['type_woning'] === 'nieuwbouw' ? 'Nieuwbouw' : 'Renovatie',
            'werk' => $werk,
            'wand' => $d['wand'],
            'plafond' => $d['plafond'],
            // Zonder complete prijs tonen we geen losse m²: de klant krijgt dan de melding dat we samen rekenen.
            'totaal' => ($total > 0 && $d['pricing'] !== null) ? $total : null,
            'stucwerk' => $p['stucwerk'] ?? null,
            'schilderwerk' => $p['schilderwerk'] ?? null,
            'dagen' => $d['pricing']['dagen'] ?? null,
            'price_hidden' => $hidden,
            'price_message' => $hidden ? $hiddenMessage : '',
            'plaats' => $d['locality'],
        ];
    }

    /** "wanden", "plafonds" of "wanden en plafonds". */
    public static function surfaceText(array $d): string
    {
        return ['wanden' => 'wanden', 'plafonds' => 'plafonds'][$d['onderdelen'] ?? 'beide'] ?? 'wanden en plafonds';
    }

    const PERIOD_CHOICES = ['kort' => 'Op korte termijn', '3m' => 'Binnen 3 maanden', '6m' => '3-6 maanden'];

    /** "27 november - 5 december" bij een gekozen datumbereik, anders de gekozen termijn ("Binnen 3 maanden"). */
    public static function periodText(array $d): string
    {
        if (($d['periode_keuze'] ?? 'weet') !== 'weet') {
            return self::PERIOD_CHOICES[$d['periode_keuze']] ?? '';
        }
        return self::periodLabel((string) $d['start'], (string) $d['eind']);
    }

    public static function periodLabel(string $start, string $end): string
    {
        [$sy, $sm, $sd] = array_map('intval', explode('-', $start));
        [$ey, $em, $ed] = array_map('intval', explode('-', $end));
        $sMonth = self::MONTHS[$sm - 1];
        $eMonth = self::MONTHS[$em - 1];
        if ($sd === $ed && $sm === $em && $sy === $ey) {
            return $sd . ' ' . $sMonth;
        }
        if ($sm === $em && $sy === $ey) {
            return $sd . ' - ' . $ed . ' ' . $sMonth;
        }
        return $sd . ' ' . $sMonth . ' - ' . $ed . ' ' . $eMonth;
    }

    private static function rooms($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (array_slice($raw, 0, 50) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $floor = NumberParser::parse(self::scalar($r['floor_m2'] ?? ''));
            $wall = NumberParser::parse(self::scalar($r['wall_m2'] ?? ''));
            if ($floor === null && $wall === null) {
                continue;
            }
            $out[] = ['name' => self::line($r['name'] ?? '', 60), 'floor_m2' => $floor, 'wall_m2' => $wall];
        }
        return $out;
    }

    private static function scalar($v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    private static function line($v, int $max): string
    {
        $s = trim(preg_replace('/\s+/', ' ', strip_tags(self::scalar($v))));
        return mb_substr($s, 0, $max);
    }

    private static function text($v, int $max): string
    {
        $s = trim(strip_tags(str_replace("\r\n", "\n", self::scalar($v))));
        return mb_substr($s, 0, $max);
    }
}
