<?php
namespace OfferteWizard;

/** Prijsindicatie (range) per onderdeel, inclusief arbeid, materiaal en BTW. */
final class Pricing
{
    /**
     * @return array{stucwerk:?array{min:int,max:int},schilderwerk:?array{min:int,max:int},dagen:?array{min:int,max:int}}
     */
    public static function compute(float $wand, float $plafond, bool $schilderen, array $s): array
    {
        $total = $wand + $plafond;
        if ($total <= 0) {
            return ['stucwerk' => null, 'schilderwerk' => null, 'dagen' => null];
        }
        $stuc = [
            'min' => (int) round($wand * (float) $s['price_stuc_wand_min'] + $plafond * (float) $s['price_stuc_plafond_min']),
            'max' => (int) round($wand * (float) $s['price_stuc_wand_max'] + $plafond * (float) $s['price_stuc_plafond_max']),
        ];
        $schilder = null;
        if ($schilderen) {
            $schilder = [
                'min' => (int) round($total * (float) $s['price_schilder_min']),
                'max' => (int) round($total * (float) $s['price_schilder_max']),
            ];
        }
        $days = max(1, (int) ceil($total / 100));
        return ['stucwerk' => $stuc, 'schilderwerk' => $schilder, 'dagen' => ['min' => $days, 'max' => $days + 1]];
    }
}
