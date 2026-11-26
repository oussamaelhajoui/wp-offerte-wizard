<?php
namespace OfferteWizard;

final class UploadPolicy
{
    /** Bepaalt het bestandstype aan de hand van de eerste bytes (niet aan de naam). */
    public static function sniff(string $head): ?string
    {
        if (strncmp($head, '%PDF', 4) === 0) {
            return 'pdf';
        }
        if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
            return 'jpg';
        }
        if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'png';
        }
        if (strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            return 'webp';
        }
        return null;
    }

    public static function check(int $size, int $maxBytes, ?string $ext): ?string
    {
        if ($ext === null) {
            return 'Dit bestandstype wordt niet ondersteund. Kies een PDF, JPG, PNG of WEBP.';
        }
        if ($size <= 0) {
            return 'Het bestand is leeg.';
        }
        if ($size > $maxBytes) {
            return 'Het bestand is te groot (maximaal ' . (int) round($maxBytes / 1048576) . ' MB).';
        }
        return null;
    }
}
