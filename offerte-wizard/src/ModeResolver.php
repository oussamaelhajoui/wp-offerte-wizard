<?php
namespace OfferteWizard;

/** Bepaalt welke extractie-methodes (in volgorde) voor een bestand gelden. */
final class ModeResolver
{
    /** @return string[] lege lijst = alleen bijlage (handmatig) */
    public static function chain(string $mode, bool $hasKey, string $ext): array
    {
        $ai = $hasKey ? ['ai'] : [];
        $text = $ext === 'pdf' ? ['tekst'] : [];
        switch ($mode) {
            case 'ai':
                return $ai;
            case 'tekst':
                return $text;
            case 'handmatig':
                return [];
            default:
                return array_merge($ai, $text);
        }
    }
}
