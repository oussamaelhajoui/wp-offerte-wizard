<?php
namespace OfferteWizard;

/**
 * Leest ruimtes uit de tekstlaag van een vector-PDF, zonder externe dienst.
 * Werkt alleen als de tekening "Woonkamer 24,5 m2" of "Keuken 3.50 x 2.80" bevat.
 */
final class TextExtractor implements PlanExtractor
{
    public function extract(string $path, string $ext): array
    {
        if ($ext !== 'pdf') {
            throw new ExtractionFailed('Alleen PDF-bestanden hebben een tekstlaag.');
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new ExtractionFailed('Bestand niet te lezen.');
        }
        return self::parseText(self::pdfText($bytes));
    }

    public static function pdfText(string $bytes): string
    {
        if (!preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $m)) {
            return '';
        }
        $out = [];
        foreach ($m[1] as $raw) {
            $data = @gzuncompress($raw);
            if ($data === false) {
                $data = @gzinflate(substr($raw, 2));
            }
            if ($data === false) {
                $data = $raw;
            }
            if (strpos($data, 'BT') === false || !preg_match_all('/BT(.*?)ET/s', $data, $blocks)) {
                continue;
            }
            foreach ($blocks[1] as $block) {
                $line = [];
                if (preg_match_all('/\[(.*?)\]\s*TJ|\((.*?)(?<!\\\\)\)\s*Tj/s', $block, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $x) {
                        if (isset($x[1]) && $x[1] !== '') {
                            preg_match_all('/\((.*?)(?<!\\\\)\)/s', $x[1], $parts);
                            $line[] = implode('', array_map([self::class, 'unescape'], $parts[1]));
                        } elseif (isset($x[2])) {
                            $line[] = self::unescape($x[2]);
                        }
                    }
                }
                if ($line) {
                    $out[] = implode(' ', $line);
                }
            }
        }
        return implode("\n", $out);
    }

    /** @return array<int,array{name:string,length_m:?float,width_m:?float,floor_area_m2:?float}> */
    public static function parseText(string $text): array
    {
        $rooms = [];
        $label = '[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\- ]{1,28}?';
        if (preg_match_all('/(' . $label . ')\s+(\d{1,3}(?:[.,]\d{1,2})?)\s*m(?:²|2)(?!\d)/u', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $a = NumberParser::parse($x[2]);
                if ($a !== null && $a < 500) {
                    $rooms[] = ['name' => trim($x[1]), 'length_m' => null, 'width_m' => null, 'floor_area_m2' => $a];
                }
            }
        }
        if (!$rooms && preg_match_all('/(?:(' . $label . ')\s+)?(\d{1,2}[.,]\d{1,2})\s*[x×]\s*(\d{1,2}[.,]\d{1,2})/u', $text, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $x) {
                $l = NumberParser::parse($x[2]);
                $w = NumberParser::parse($x[3]);
                if ($l !== null && $w !== null) {
                    $rooms[] = ['name' => trim($x[1] ?? ''), 'length_m' => $l, 'width_m' => $w, 'floor_area_m2' => null];
                }
            }
        }
        return $rooms;
    }

    private static function unescape(string $s): string
    {
        return str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
    }
}
