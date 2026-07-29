<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Services\Sms\AfricasTalkingGateway;
use App\Services\Sms\TwilioGateway;

class SmsService
{
    protected SmsGateway $gateway;

    public function __construct(?SmsGateway $gateway = null)
    {
        $this->gateway = $gateway ?? $this->resolveGateway();
    }

    protected function resolveGateway(): SmsGateway
    {
        return match (config('services.sms.default', 'africastalking')) {
            'twilio' => new TwilioGateway(),
            default  => new AfricasTalkingGateway(),
        };
    }

    /**
     * Normalize a Ugandan phone number to E.164 form (+256XXXXXXXXX). Client::phone
     * has no enforced format in this app, so numbers may arrive as 07XXXXXXXX,
     * 256XXXXXXXXX, 7XXXXXXXX, or already +256XXXXXXXXX.
     */
    public function normalizePhone(?string $phone): ?string
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

    /**
     * Send one SMS via whichever gateway is configured (services.sms.default).
     * Returns ['success' => bool, 'message' => string].
     */
    public function send(string $phone, string $message): array
    {
        $to = $this->normalizePhone($phone);
        if (!$to) {
            return ['success' => false, 'message' => "Unrecognized phone number format: {$phone}"];
        }

        return $this->gateway->send($to, $message);
    }
}
