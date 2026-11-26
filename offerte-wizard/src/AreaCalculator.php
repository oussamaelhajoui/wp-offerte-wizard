<?php
namespace OfferteWizard;

/** Rekent ruimtes uit het bouwplan om naar plafond- en wand-m². */
final class AreaCalculator
{
    /** @return array{name:string,floor_m2:float,wall_m2:float}|null */
    public static function room(array $r, float $height): ?array
    {
        $l = self::num($r['length_m'] ?? null);
        $w = self::num($r['width_m'] ?? null);
        $a = self::num($r['floor_area_m2'] ?? null);
        if ($a <= 0 && $l > 0 && $w > 0) {
            $a = $l * $w;
        }
        if ($a <= 0) {
            return null;
        }
        $perimeter = ($l > 0 && $w > 0) ? 2 * ($l + $w) : 4 * sqrt($a);
        return [
            'name' => trim((string) ($r['name'] ?? '')),
            'floor_m2' => round($a, 2),
            'wall_m2' => round($perimeter * $height, 2),
        ];
    }

    /** @return array{rooms:array,ceiling_m2:float,wall_m2:float,skipped:int} */
    public static function totals(array $rawRooms, float $height): array
    {
        $rooms = [];
        $skipped = 0;
        foreach ($rawRooms as $r) {
            $room = is_array($r) ? self::room($r, $height) : null;
            if ($room === null) {
                $skipped++;
                continue;
            }
            $rooms[] = $room;
        }
        $ceiling = 0.0;
        $walls = 0.0;
        foreach ($rooms as $room) {
            $ceiling += $room['floor_m2'];
            $walls += $room['wall_m2'];
        }
        return ['rooms' => $rooms, 'ceiling_m2' => round($ceiling, 1), 'wall_m2' => round($walls, 1), 'skipped' => $skipped];
    }

    private static function num($v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }
}
