<?php
namespace OfferteWizard;

/** Leest een door de klant ingetypt getal ("12,5 m2", "1.200") streng uit. */
final class NumberParser
{
    public static function parse(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $s = strtolower(trim($raw));
        $s = str_replace(['vierkante meter', 'vierkant', 'm²', 'm^2', 'm2'], '', $s);
        $s = trim($s);
        if ($s === '' || !preg_match('/^\d[\d.,\s]*$/', $s)) {
            return null;
        }
        $s = str_replace(' ', '', $s);
        $hasComma = strpos($s, ',') !== false;
        $hasDot = strpos($s, '.') !== false;
        if ($hasComma && $hasDot && strrpos($s, '.') > strrpos($s, ',')) {
            return null;
        }
        if ($hasComma) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        if (!is_numeric($s)) {
            return null;
        }
        $v = (float) $s;
        return ($v > 0 && $v <= 100000) ? $v : null;
    }
}
