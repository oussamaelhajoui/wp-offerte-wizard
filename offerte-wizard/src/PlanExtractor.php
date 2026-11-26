<?php
namespace OfferteWizard;

interface PlanExtractor
{
    /**
     * @return array<int,array{name:string,length_m:?float,width_m:?float,floor_area_m2:?float}>
     * @throws ExtractionFailed
     */
    public function extract(string $path, string $ext): array;
}
