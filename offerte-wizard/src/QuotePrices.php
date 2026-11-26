<?php
namespace OfferteWizard;

/** Bepaalt de prijzen in de offerte: eigen prijs van de eigenaar, anders de berekende indicatie. */
final class QuotePrices
{
    /**
     * @param array|null $pricing uitkomst van Pricing::compute
     * @param array $custom st_min, st_max, sc_min, sc_max, note (alles optioneel)
     * @return array{stucwerk:?array{min:int,max:int},schilderwerk:?array{min:int,max:int},note:string}
     */
    public static function resolve(?array $pricing, array $custom): array
    {
        return [
            'stucwerk' => self::range($custom['st_min'] ?? '', $custom['st_max'] ?? '') ?? ($pricing['stucwerk'] ?? null),
            'schilderwerk' => self::range($custom['sc_min'] ?? '', $custom['sc_max'] ?? '') ?? ($pricing['schilderwerk'] ?? null),
            'note' => mb_substr(trim(strip_tags((string) ($custom['note'] ?? ''))), 0, 2000),
        ];
    }

    /** Eén ingevuld bedrag geldt als vaste prijs. @return array{min:int,max:int}|null */
    private static function range($min, $max): ?array
    {
        $a = NumberParser::parse(is_scalar($min) ? (string) $min : '');
        $b = NumberParser::parse(is_scalar($max) ? (string) $max : '');
        if ($a === null && $b === null) {
            return null;
        }
        $a = $a ?? $b;
        $b = $b ?? $a;
        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }
        return ['min' => (int) round($a), 'max' => (int) round($b)];
    }
}
