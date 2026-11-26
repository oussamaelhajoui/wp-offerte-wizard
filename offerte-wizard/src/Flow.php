<?php
namespace OfferteWizard;

/**
 * De volledige wizard als data. PHP (validatie, volgende stap) en de
 * browser (rendering) gebruiken exact dezelfde definitie.
 */
final class Flow
{
    const ERR_CHOICE = 'Maak een keuze om door te gaan!';
    const ERR_NUMBER = 'Vul een getal in, bijvoorbeeld 120.';
    const ERR_UPLOAD = 'Upload een bouwplan om door te gaan.';

    const ONDERGROND = ['baksteen', 'gips', 'beton'];

    /** @return array<int,array<string,mixed>> */
    public static function steps(): array
    {
        $n = static function (string $default, array $when = []): array {
            return ['when' => $when, 'default' => $default];
        };
        // Een regel geldt als ALLE voorwaarden kloppen: $w([['m2', 'ja'], ['onderdelen', 'wanden']], 'volgende-stap').
        $w = static function (array $conditions, string $goto): array {
            return ['all' => array_map(static function (array $c): array {
                return ['field' => $c[0], 'eq' => $c[1]];
            }, $conditions), 'goto' => $goto];
        };
        return [
            [
                'id' => 'welkom', 'type' => 'welcome', 'phase' => 0,
                'title' => 'Vraag je offerte aan', 'subtitle' => 'Dit duurt maar 3 minuten.',
                'lead' => 'Zo helpen we je verder:',
                'items' => [
                    ['icon' => 'tag', 'title' => 'Een scherpe prijs', 'text' => 'We bieden je een scherpe prijs voor je klus.'],
                    ['icon' => 'calendar', 'title' => 'Beschikbaarheid', 'text' => 'We kijken samen naar de beschikbaarheid.'],
                    ['icon' => 'clock', 'title' => 'Klusdagen', 'text' => 'We vertellen je hoe lang de klus duurt.'],
                    ['icon' => 'list', 'title' => 'Checklist', 'text' => 'We maken samen een checklist voor de voorbereidingen.'],
                ],
                'cta' => 'Start je aanvraag', 'next' => $n('type-woning'),
            ],
            [
                'id' => 'type-woning', 'type' => 'choice', 'phase' => 1, 'field' => 'type_woning',
                'title' => 'Nieuwbouw of renovatie?', 'subtitle' => 'Selecteer één van de opties.',
                'options' => [
                    ['value' => 'nieuwbouw', 'label' => 'Nieuwbouw', 'icon' => 'crane'],
                    ['value' => 'renovatie', 'label' => 'Renovatie', 'icon' => 'tools'],
                ],
                'next' => $n('onderdelen'),
            ],
            [
                'id' => 'onderdelen', 'type' => 'choice', 'phase' => 1, 'field' => 'onderdelen',
                'title' => 'Wat moet er gestukt worden?', 'subtitle' => 'Selecteer één van de opties.',
                'options' => [
                    ['value' => 'wanden', 'label' => 'Alleen wanden', 'icon' => 'board'],
                    ['value' => 'plafonds', 'label' => 'Alleen plafonds', 'icon' => 'flat'],
                    ['value' => 'beide', 'label' => 'Wanden en plafonds', 'icon' => 'house'],
                ],
                'next' => $n('m2'),
            ],
            [
                'id' => 'm2', 'type' => 'choice', 'phase' => 1, 'field' => 'm2',
                'title' => 'Weet je hoeveel m² er gestukt moet worden?', 'subtitle' => 'Selecteer één van de opties.',
                'note' => 'Geen zorgen als je het niet precies weet. Een inschatting is genoeg, we meten alles nog in op locatie.',
                'options' => [
                    ['value' => 'ja', 'label' => 'Ja, ik weet het ongeveer', 'sub' => 'Een ruwe schatting is genoeg', 'icon' => 'check'],
                    ['value' => 'nee', 'label' => 'Nee, help mij even', 'sub' => 'We berekenen dit later samen', 'icon' => 'ruler'],
                    ['value' => 'bouwplan', 'label' => 'Ik heb een bouwplan', 'sub' => 'Upload je tekening, wij lezen de m² uit', 'icon' => 'plan', 'requires' => 'bouwplan'],
                ],
                'next' => $n('m2-wand', [
                    $w([['m2', 'nee']], 'wand-ondergrond'),
                    $w([['m2', 'bouwplan']], 'bouwplan-upload'),
                    $w([['m2', 'ja'], ['onderdelen', 'plafonds']], 'm2-plafond'),
                ]),
            ],
            [
                'id' => 'm2-wand', 'type' => 'number', 'phase' => 2, 'field' => 'm2_wand',
                'title' => 'Vul m² voor wanden in',
                'subtitle' => 'Lengte x hoogte per wand (deurposten & kozijnen meegerekend) en tel bij elkaar op.',
                'placeholder' => '200m2', 'hint' => ['title' => 'Ben je niet helemaal zeker?', 'text' => 'Een ruwe schatting is ook goed!'],
                'next' => $n('m2-plafond', [$w([['onderdelen', 'wanden']], 'schilderen')]),
            ],
            [
                'id' => 'm2-plafond', 'type' => 'number', 'phase' => 2, 'field' => 'm2_plafond',
                'title' => 'Vul m² voor plafonds in', 'subtitle' => 'Lengte x breedte per plafond en tel bij elkaar op.',
                'placeholder' => '200m2', 'hint' => ['title' => 'Ben je niet helemaal zeker?', 'text' => 'Een ruwe schatting is ook goed!'],
                'next' => $n('type-plafond'),
            ],
            [
                'id' => 'type-plafond', 'type' => 'choice', 'phase' => 2, 'field' => 'daktype',
                'title' => 'Hoe ziet je dak eruit?', 'subtitle' => 'Selecteer één van de opties.',
                'options' => [
                    ['value' => 'punt', 'label' => 'Punt dak', 'icon' => 'house'],
                    ['value' => 'plat', 'label' => 'Plat dak', 'icon' => 'flat'],
                    ['value' => 'appartement', 'label' => 'Appartement', 'icon' => 'building'],
                ],
                'next' => $n('schilderen'),
            ],
            [
                'id' => 'wand-ondergrond', 'type' => 'multi', 'phase' => 2, 'field' => 'ondergrond',
                'title' => 'Welke ondergrond heb jij?', 'subtitle' => 'Zo bepalen we het juiste stucwerk.',
                'note' => 'Meerdere opties mogelijk.',
                'options' => [
                    ['value' => 'baksteen', 'label' => 'Baksteen of Gevel', 'icon' => 'brick'],
                    ['value' => 'gips', 'label' => 'Gipsplaten of Korrels', 'icon' => 'board'],
                    ['value' => 'beton', 'label' => 'Beton', 'icon' => 'block'],
                ],
                'next' => $n('schilderen'),
            ],
            [
                'id' => 'bouwplan-upload', 'type' => 'upload', 'phase' => 2,
                'title' => 'Upload je bouwplan', 'subtitle' => 'PDF of foto van de plattegrond, het liefst met maatvoering.',
                'next' => $n('bouwplan-controle'),
            ],
            [
                'id' => 'bouwplan-controle', 'type' => 'review', 'phase' => 2,
                'title' => 'Klopt dit?', 'subtitle' => 'Controleer de afmetingen en pas aan waar nodig.',
                'next' => $n('type-plafond', [$w([['onderdelen', 'wanden']], 'schilderen')]),
            ],
            [
                'id' => 'schilderen', 'type' => 'choice', 'phase' => 2, 'field' => 'schilderen',
                'title' => 'Wil je ook schilderen?', 'subtitle' => 'Selecteer één van de opties.',
                'options' => [
                    ['value' => 'ja', 'label' => 'Graag!', 'sub' => 'Ik wil mijn woning laten schilderen', 'icon' => 'check'],
                    ['value' => 'nee', 'label' => 'Niet nodig', 'sub' => 'Niet nodig, ik doe het zelf', 'icon' => 'cross'],
                ],
                'next' => $n('periode'),
            ],
            [
                'id' => 'periode', 'type' => 'choice', 'phase' => 2, 'field' => 'periode_keuze',
                'title' => 'Weet je al in welke periode?', 'subtitle' => 'Kies wat het beste past.',
                'options' => [
                    ['value' => 'weet', 'label' => 'Ik weet het', 'sub' => 'Ik kies zelf een periode', 'icon' => 'check'],
                    ['value' => 'kort', 'label' => 'Op korte termijn', 'icon' => 'clock'],
                    ['value' => '3m', 'label' => 'Binnen 3 maanden', 'icon' => 'calendar'],
                    ['value' => '6m', 'label' => '3-6 maanden', 'icon' => 'calendar'],
                ],
                'next' => $n('contactgegevens', [$w([['periode_keuze', 'weet']], 'kalender')]),
            ],
            [
                'id' => 'kalender', 'type' => 'daterange', 'phase' => 2,
                'title' => 'Wanneer wil je starten?', 'subtitle' => 'Selecteer de gewenste periode.',
                'hint' => ['title' => 'Nog niet helemaal zeker?', 'text' => 'Een indicatie helpt ons al enorm.'],
                'next' => $n('contactgegevens'),
            ],
            [
                'id' => 'contactgegevens', 'type' => 'contact', 'phase' => 3,
                'title' => 'Bijna klaar! Waar sturen we je offerte naartoe?',
                'subtitle' => 'Nog een paar gegevens en je prijs staat klaar.',
                'fields' => [
                    ['kind' => 'field', 'name' => 'first_name', 'label' => 'Voornaam:', 'placeholder' => 'Bijvoorbeeld: Johan', 'input' => 'text', 'autocomplete' => 'given-name'],
                    ['kind' => 'field', 'name' => 'last_name', 'label' => 'Achternaam:', 'placeholder' => 'Bijvoorbeeld: Jansen', 'input' => 'text', 'autocomplete' => 'family-name'],
                    ['kind' => 'field', 'name' => 'email', 'label' => 'E-mailadres:', 'placeholder' => 'Bijvoorbeeld: jan@gmail.com', 'input' => 'email', 'autocomplete' => 'email', 'hint' => 'Hier sturen we je offerte naartoe.'],
                    ['kind' => 'field', 'name' => 'phone_number', 'label' => 'Telefoonnummer:', 'placeholder' => 'Bijvoorbeeld: 0656112233', 'input' => 'tel', 'autocomplete' => 'tel'],
                    ['kind' => 'note', 'title' => 'Het begint met een persoonlijk belletje van iemand uit ons team.', 'text' => 'Dan bespreken we je klus, planning, budget en vragen. Zo weet je precies waar je aan toe bent en word je klus een stuk overzichtelijker.'],
                    ['kind' => 'heading', 'text' => 'Vermeld hieronder waar de klus uitgevoerd moet worden.'],
                    ['kind' => 'field', 'name' => 'address_line', 'label' => 'Straat en huisnummer:', 'placeholder' => 'Bijvoorbeeld: Bloemstraat 11', 'input' => 'text', 'autocomplete' => 'street-address'],
                    ['kind' => 'field', 'name' => 'locality', 'label' => 'Plaats:', 'placeholder' => 'Bijvoorbeeld: Eindhoven', 'input' => 'text', 'autocomplete' => 'address-level2'],
                    ['kind' => 'field', 'name' => 'post_code', 'label' => 'Postcode:', 'placeholder' => 'Bijvoorbeeld: 5611 AB', 'input' => 'text', 'autocomplete' => 'postal-code'],
                ],
                'next' => $n('beschrijving'),
            ],
            [
                'id' => 'beschrijving', 'type' => 'text', 'phase' => 3, 'field' => 'beschrijving',
                'title' => 'Nog bijzonderheden?', 'subtitle' => 'Alle extra informatie helpt ons om je beter te kunnen helpen.',
                'placeholder' => 'Beschrijving', 'cta' => 'Versturen', 'submit' => true,
                'next' => $n('overzicht'),
            ],
            ['id' => 'overzicht', 'type' => 'summary', 'phase' => 0, 'next' => null],
        ];
    }

    public static function step(string $id): ?array
    {
        foreach (self::steps() as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    public static function next(string $id, array $answers): ?string
    {
        $step = self::step($id);
        if ($step === null || empty($step['next'])) {
            return null;
        }
        foreach ($step['next']['when'] as $rule) {
            $match = true;
            foreach ($rule['all'] as $c) {
                if (!isset($answers[$c['field']]) || (string) $answers[$c['field']] !== $c['eq']) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $rule['goto'];
            }
        }
        return $step['next']['default'];
    }

    /**
     * Welke oppervlakken de klant wil laten stucen.
     * @return array{wand:bool,plafond:bool}
     */
    public static function surfaces(array $answers): array
    {
        $o = $answers['onderdelen'] ?? 'beide';
        return ['wand' => $o !== 'plafonds', 'plafond' => $o !== 'wanden'];
    }

    /** Foutmelding voor deze stap, of null als de antwoorden kloppen. */
    public static function validate(string $id, array $answers, string $today): ?string
    {
        $step = self::step($id);
        if ($step === null) {
            return self::ERR_CHOICE;
        }
        switch ($step['type']) {
            case 'choice':
                $values = array_column($step['options'], 'value');
                $v = $answers[$step['field']] ?? null;
                return (is_string($v) && in_array($v, $values, true)) ? null : self::ERR_CHOICE;
            case 'multi':
                $v = $answers[$step['field']] ?? null;
                if (!is_array($v) || !$v) {
                    return self::ERR_CHOICE;
                }
                foreach ($v as $item) {
                    if (!is_string($item) || !in_array($item, self::ONDERGROND, true)) {
                        return self::ERR_CHOICE;
                    }
                }
                return null;
            case 'number':
                return NumberParser::parse(self::str($answers[$step['field']] ?? '')) !== null ? null : self::ERR_NUMBER;
            case 'upload':
                $t = self::str($answers['plan_token'] ?? '');
                return preg_match('/^[a-f0-9]{32}$/', $t) ? null : self::ERR_UPLOAD;
            case 'review':
                // Zonder uitgelezen ruimtes (handmatige modus) zijn de m² optioneel.
                $rooms = $answers['rooms'] ?? [];
                if (!is_array($rooms) || !$rooms) {
                    return null;
                }
                $need = self::surfaces($answers);
                $ok = (!$need['wand'] || NumberParser::parse(self::str($answers['m2_wand'] ?? '')) !== null)
                    && (!$need['plafond'] || NumberParser::parse(self::str($answers['m2_plafond'] ?? '')) !== null);
                return $ok ? null : self::ERR_NUMBER;
            case 'daterange':
                return Validator::period(self::str($answers['periode_start'] ?? ''), self::str($answers['periode_eind'] ?? ''), $today);
            case 'contact':
                $errs = Validator::contact($answers);
                return $errs ? reset($errs) : null;
        }
        return null;
    }

    private static function str($v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
