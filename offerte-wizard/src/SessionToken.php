<?php
namespace OfferteWizard;

/**
 * Cache-veilig alternatief voor een nonce: wordt bij het laden van de wizard
 * opgehaald via REST (nooit uit een gecachte pagina) en is pas na een paar
 * seconden geldig, wat simpele bots tegenhoudt.
 */
final class SessionToken
{
    public static function issue(string $secret, int $now): string
    {
        return $now . '.' . hash_hmac('sha256', (string) $now, $secret);
    }

    public static function verify(string $token, string $secret, int $now, int $minAge = 3, int $maxAge = 86400): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', $parts[0], $secret), $parts[1])) {
            return false;
        }
        $age = $now - (int) $parts[0];
        return $age >= $minAge && $age <= $maxAge;
    }
}
