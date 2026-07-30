<?php

namespace App\Http\Controllers;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarzPayWebhookController extends Controller
{
    public function __construct(protected MobileMoneyService $mobileMoneyService) {}

    /**
     * MarzPay's webhook signature scheme isn't documented, so this payload is NEVER trusted
     * for anything sensitive -- it's only used to identify WHICH transaction to look at.
     * The actual status is always re-verified via an authenticated call back to MarzPay
     * before any money moves (see MobileMoneyService::reconcile()).
     */
    public function handle(Request $request)
    {
        Log::info('MarzPay webhook received', $request->all());

        $reference = $request->input('transaction.reference') ?? $request->input('reference');
        $uuid      = $request->input('transaction.uuid') ?? $request->input('transaction_uuid');

        $mm = null;
        if ($reference) {
            $mm = MobileMoneyTransaction::where('reference', $reference)->first();
        }
        if (!$mm && $uuid) {
            $mm = MobileMoneyTransaction::where('provider_reference', $uuid)->first();
        }

        if ($mm) {
            $this->mobileMoneyService->reconcile($mm);
        }

        return response()->json(['status' => 'ok']);
    }
}
