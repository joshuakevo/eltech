<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Services\Sms\AfricasTalkingGateway;
use App\Services\Sms\MarzSmsGateway;
use App\Services\Sms\TwilioGateway;
use App\Support\PhoneNumber;

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
            'twilio'  => new TwilioGateway(),
            'marzsms' => new MarzSmsGateway(),
            default   => new AfricasTalkingGateway(),
        };
    }

    /**
     * Normalize a Ugandan phone number to E.164 form (+256XXXXXXXXX).
     */
    public function normalizePhone(?string $phone): ?string
    {
        return PhoneNumber::normalize($phone);
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
