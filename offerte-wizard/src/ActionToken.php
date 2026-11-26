<?php
namespace OfferteWizard;

/** Ondertekende acties voor de knoppen in de mail aan de eigenaar. */
final class ActionToken
{
    const TTL = 2592000; // 30 dagen

    public static function sign(int $id, string $action, int $exp, string $secret): string
    {
        return hash_hmac('sha256', $id . '|' . $action . '|' . $exp, $secret);
    }

    public static function verify(int $id, string $action, int $exp, string $sig, string $secret, int $now): bool
    {
        return $id > 0 && $exp >= $now && hash_equals(self::sign($id, $action, $exp, $secret), $sig);
    }
}
