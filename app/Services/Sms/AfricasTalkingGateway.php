<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingGateway implements SmsGateway
{
    protected string $username;
    protected ?string $apiKey;
    protected ?string $senderId;
    protected bool $sandbox;

    public function __construct()
    {
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->apiKey   = config('services.africastalking.api_key');
        $this->senderId = config('services.africastalking.sender_id') ?: null;
        $this->sandbox  = $this->username === 'sandbox';
    }

    protected function endpoint(): string
    {
        return $this->sandbox
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';
    }

    public function send(string $to, string $message): array
    {
        if (!$this->apiKey) {
            return ['success' => false, 'message' => "Africa's Talking API key is not configured."];
        }

        $payload = [
            'username' => $this->username,
            'to'       => $to,
            'message'  => $message,
        ];
        if ($this->senderId) {
            $payload['from'] = $this->senderId;
        }

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'apiKey' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($this->endpoint(), $payload);

            $data       = $response->json();
            $recipients = $data['SMSMessageData']['Recipients'] ?? [];
            $first      = $recipients[0] ?? null;

            if ($response->successful() && $first && ($first['status'] ?? '') === 'Success') {
                $cost = $first['cost'] ?? 'n/a';
                return ['success' => true, 'message' => "Sent (cost: {$cost})"];
            }

            $errorMsg = $first['status'] ?? ($data['SMSMessageData']['Message'] ?? $response->body());
            return ['success' => false, 'message' => $errorMsg];
        } catch (\Throwable $e) {
            Log::error('Africa\'s Talking SMS send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
