<?php
namespace OfferteWizard;

final class Validator
{
    const MSG_ALL = 'Vul alle velden in';
    const MSG_EMAIL = 'Vul een geldig e-mailadres in.';
    const MSG_PHONE = 'Vul een geldig telefoonnummer in.';
    const MSG_POSTCODE = 'Vul een geldige postcode in.';
    const MSG_PERIOD = 'Selecteer een periode';

    const CONTACT_FIELDS = ['first_name', 'last_name', 'email', 'phone_number', 'address_line', 'locality', 'post_code'];

    /** @return array<string,string> veld => foutmelding (leeg = geldig) */
    public static function contact(array $in): array
    {
        $errors = [];
        foreach (self::CONTACT_FIELDS as $f) {
            if (trim((string) ($in[$f] ?? '')) === '') {
                $errors[$f] = self::MSG_ALL;
            }
        }
        if (!isset($errors['email']) && !filter_var(trim((string) $in['email']), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = self::MSG_EMAIL;
        }
        if (!isset($errors['phone_number'])) {
            $p = preg_replace('/[\s\-().]/', '', (string) $in['phone_number']);
            if (!preg_match('/^(?:0|\+31|0031)[1-9]\d{8}$/', $p)) {
                $errors['phone_number'] = self::MSG_PHONE;
            }
        }
        if (!isset($errors['post_code']) && !preg_match('/^[1-9]\d{3}\s?[a-zA-Z]{2}$/', trim((string) $in['post_code']))) {
            $errors['post_code'] = self::MSG_POSTCODE;
        }
        return $errors;
    }

    public static function period(string $start, string $end, string $today): ?string
    {
        if (!self::isDate($start) || !self::isDate($end)) {
            return self::MSG_PERIOD;
        }
        if ($start < $today || $end < $start) {
            return self::MSG_PERIOD;
        }
        return null;
    }

    public static function isDate(string $d): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
