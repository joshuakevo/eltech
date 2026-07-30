<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarzSmsGateway implements SmsGateway
{
    protected ?string $apiKey;
    protected ?string $secret;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.marzsms.api_key');
        $this->secret  = config('services.marzsms.secret');
        $this->baseUrl = rtrim(config('services.marzsms.base_url', 'https://sms.wearemarz.com/api/v1'), '/');
    }

    public function send(string $to, string $message): array
    {
        if (!$this->apiKey || !$this->secret) {
            return ['success' => false, 'message' => 'MarzSMS API key/secret is not configured.'];
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secret)
                ->acceptJson()
                ->post("{$this->baseUrl}/sms/send", [
                    'recipient' => $to,
                    'message'   => $message,
                ]);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                $successful = $data['data']['successful'] ?? 1;
                $cost       = $data['data']['total_cost'] ?? 'n/a';
                $balance    = $data['data']['remaining_balance'] ?? 'n/a';
                return ['success' => (bool) $successful, 'message' => "Sent (cost: {$cost}, balance: {$balance})"];
            }

            $errorMsg = $data['message'] ?? $response->body();
            return ['success' => false, 'message' => $errorMsg];
        } catch (\Throwable $e) {
            Log::error('MarzSMS send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
