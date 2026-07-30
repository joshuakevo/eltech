<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a Ugandan phone number to E.164 form (+256XXXXXXXXX). Client::phone
     * has no enforced format in this app, so numbers may arrive as 07XXXXXXXX,
     * 256XXXXXXXXX, 7XXXXXXXX, or already +256XXXXXXXXX. Used before handing a number
     * to any external gateway (SMS or MarzPay) that requires a specific format.
     */
    public static function normalize(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        if (str_starts_with(trim($phone), '+')) {
            $digits = preg_replace('/\D/', '', $phone);
            return $digits ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+256' . substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '256')) {
            return '+' . $digits;
        }
        if (strlen($digits) === 9) {
            return '+256' . $digits;
        }

        return null;
    }
}
