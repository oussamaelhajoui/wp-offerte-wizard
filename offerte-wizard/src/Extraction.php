<?php
namespace OfferteWizard;

/** Probeert de extractors in volgorde; bij niets valt het terug op handmatig. */
final class Extraction
{
    /**
     * @param string[] $chain
     * @param array<string,PlanExtractor> $extractors
     * @return array{rooms:array,source:string}
     */
    public static function run(array $chain, array $extractors, string $path, string $ext): array
    {
        foreach ($chain as $name) {
            if (!isset($extractors[$name])) {
                continue;
            }
            try {
                $rooms = $extractors[$name]->extract($path, $ext);
            } catch (\Throwable $e) {
                continue;
            }
            if ($rooms) {
                return ['rooms' => $rooms, 'source' => $name];
            }
        }
        return ['rooms' => [], 'source' => 'handmatig'];
    }
}
