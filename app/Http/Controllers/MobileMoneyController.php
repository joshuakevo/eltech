<?php

namespace App\Http\Controllers;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoneyService;
use Illuminate\Http\Request;

class MobileMoneyController extends Controller
{
    public function __construct(protected MobileMoneyService $mobileMoneyService) {}

    public function index(Request $request)
    {
        // Opportunistically re-check every non-final transaction against MarzPay on each
        // page load. This is the reliable path -- shared hosting here has no guaranteed
        // cron, and the live JS polling only runs while a tab is open, so a withdrawal
        // approved outside that window would otherwise sit "processing" until someone
        // clicks "Refresh Status" manually.
        MobileMoneyTransaction::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '>=', now()->subDays(3))
            ->get()
            ->each(fn ($mm) => $this->mobileMoneyService->reconcile($mm));

        $transactions = MobileMoneyTransaction::with(['client', 'savingsAccount', 'approvedBy'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(30);

        return view('mobile-money.index', compact('transactions'));
    }

    public function approve(Request $request, MobileMoneyTransaction $mobileMoneyTransaction)
    {
        $result = $this->mobileMoneyService->approveWithdrawal($mobileMoneyTransaction, $request->user());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reject(MobileMoneyTransaction $mobileMoneyTransaction)
    {
        $result = $this->mobileMoneyService->rejectWithdrawal($mobileMoneyTransaction);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function refresh(Request $request, MobileMoneyTransaction $mobileMoneyTransaction)
    {
        $this->mobileMoneyService->reconcile($mobileMoneyTransaction);
        $mobileMoneyTransaction->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'status'         => $mobileMoneyTransaction->status,
                'failure_reason' => $mobileMoneyTransaction->failure_reason,
            ]);
        }

        return back()->with('success', 'Status refreshed: ' . $mobileMoneyTransaction->status);
    }
}
