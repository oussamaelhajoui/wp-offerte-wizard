<?php
namespace OfferteWizard;

final class RateLimiter
{
    /**
     * @param int[] $bucket tijdstippen van eerdere pogingen (wordt bijgewerkt)
     */
    public static function allow(array &$bucket, int $now, int $limit, int $window): bool
    {
        $bucket = array_values(array_filter($bucket, static function ($t) use ($now, $window) {
            return $t > $now - $window;
        }));
        if (count($bucket) >= $limit) {
            return false;
        }
        $bucket[] = $now;
        return true;
    }
}
