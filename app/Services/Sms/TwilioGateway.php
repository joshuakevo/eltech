<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioGateway implements SmsGateway
{
    protected ?string $sid;
    protected ?string $token;
    protected ?string $from;

    public function __construct()
    {
        $this->sid   = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->from  = config('services.twilio.from');
    }

    public function send(string $to, string $message): array
    {
        if (!$this->sid || !$this->token || !$this->from) {
            return ['success' => false, 'message' => 'Twilio is not fully configured (SID, auth token, or from-number missing).'];
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->sid, $this->token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                    'To'   => $to,
                    'From' => $this->from,
                    'Body' => $message,
                ]);

            $data = $response->json();

            if ($response->successful()) {
                $status = $data['status'] ?? 'queued';
                return ['success' => true, 'message' => "Sent (status: {$status})"];
            }

            $errorMsg = $data['message'] ?? $response->body();
            return ['success' => false, 'message' => $errorMsg];
        } catch (\Throwable $e) {
            Log::error('Twilio SMS send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
