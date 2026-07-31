<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarzPayService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.marzpay.base_url', 'https://wallet.wearemarz.com/api/v1'), '/');
        $this->apiKey    = config('services.marzpay.api_key');
        $this->apiSecret = config('services.marzpay.api_secret');
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->apiKey && $this->apiSecret);
    }

    protected function client()
    {
        return Http::withBasicAuth($this->apiKey, $this->apiSecret)->acceptJson();
    }

    public function generateReference(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Initiate a collection (deposit): charges the client's mobile money. The client
     * approves this themselves via a USSD/PIN prompt on their own phone -- no staff
     * action needed.
     */
    public function collect(string $phone, float $amount, string $reference, string $description, ?string $callbackUrl = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'MarzPay is not configured.'];
        }

        try {
            $payload = [
                'amount'       => (int) round($amount),
                'phone_number' => $phone,
                'country'      => 'UG',
                'reference'    => $reference,
                'description'  => $description,
            ];
            if ($callbackUrl) {
                $payload['callback_url'] = $callbackUrl;
            }

            $response = $this->client()->post("{$this->baseUrl}/collect-money", $payload);
            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'success') {
                return [
                    'success'            => true,
                    'provider_reference' => $data['data']['transaction']['uuid'] ?? null,
                    'status'             => $data['data']['transaction']['status'] ?? 'processing',
                    'message'            => $data['message'] ?? 'Collection initiated.',
                ];
            }

            return ['success' => false, 'message' => $data['message'] ?? $response->body()];
        } catch (\Throwable $e) {
            Log::error('MarzPay collect failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Initiate a disbursement (withdrawal payout): sends money to the client's mobile
     * money. EXPERIMENTAL -- MarzPay's docs do not fully specify this endpoint's request/
     * response; this mirrors the documented collect-money pattern. Treat the first real
     * use as a live test, not a confirmed-working integration.
     */
    public function disburse(string $phone, float $amount, string $reference, string $description): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'MarzPay is not configured.'];
        }

        try {
            $response = $this->client()->post("{$this->baseUrl}/send-money", [
                'amount'       => (int) round($amount),
                'phone_number' => $phone,
                'country'      => 'UG',
                'reference'    => $reference,
                'description'  => $description,
            ]);
            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'success') {
                return [
                    'success'            => true,
                    'provider_reference' => $data['data']['transaction']['uuid'] ?? null,
                    'status'             => $data['data']['transaction']['status'] ?? 'processing',
                    'message'            => $data['message'] ?? 'Disbursement initiated.',
                ];
            }

            return ['success' => false, 'message' => $data['message'] ?? $response->body()];
        } catch (\Throwable $e) {
            Log::error('MarzPay disburse failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Authoritative status check, queried directly from MarzPay using our own credentials.
     * This -- never a webhook payload's contents -- is what decides whether money actually
     * moves in our ledger.
     *
     * Accepts BOTH our own reference and MarzPay's own transaction UUID (provider_reference)
     * and tries each against both the `reference` and `uuid` filter params. This is
     * deliberately redundant: collect-money (deposits) reliably honors OUR reference as a
     * filter, but send-money (disbursements) is undocumented and there's no confirmation it
     * indexes transactions the same way -- a withdrawal that never matches here would
     * otherwise stay "processing" forever no matter how many times reconcile() runs.
     */
    public function checkStatusByReference(string $reference, ?string $providerReference = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'MarzPay is not configured.'];
        }

        $candidates = array_unique(array_filter([$reference, $providerReference]));

        $lastMessage = 'Transaction not found on MarzPay for this reference.';

        foreach ($candidates as $value) {
            foreach (['reference', 'uuid'] as $param) {
                $result = $this->lookupTransaction($param, $value);

                if ($result['success']) {
                    return $result;
                }

                $lastMessage = $result['message'];
            }
        }

        Log::warning('MarzPay status lookup exhausted all candidates', [
            'reference'          => $reference,
            'provider_reference' => $providerReference,
            'last_message'       => $lastMessage,
        ]);

        return ['success' => false, 'message' => $lastMessage];
    }

    protected function lookupTransaction(string $param, string $value): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/transactions", [
                $param     => $value,
                'per_page' => 1,
            ]);
            $data = $response->json();

            if (!$response->successful() || ($data['status'] ?? '') !== 'success') {
                return ['success' => false, 'message' => $data['message'] ?? $response->body()];
            }

            $rows = $data['data']['transactions'] ?? $data['data'] ?? [];
            $row  = is_array($rows) ? ($rows[0] ?? null) : null;

            if (!$row) {
                return ['success' => false, 'message' => 'Transaction not found on MarzPay for this reference.'];
            }

            return [
                'success' => true,
                'status'  => $row['status'] ?? $row['transaction']['status'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('MarzPay status check failed', ['param' => $param, 'value' => $value, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
