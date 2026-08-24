<?php

namespace App\Http\Controllers;

use App\Models\SmsSubscriptionPayment;
use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;

class SmsSubscriptionController extends Controller
{
    public function __construct(protected SmsSubscriptionService $subscription) {}

    public function index()
    {
        $canSend            = $this->subscription->canSend();
        $freeTrialRemaining = $this->subscription->freeTrialRemaining();
        $activeSubscription = $this->subscription->activeSubscription();
        $subscriptionPrice  = $this->subscription->subscriptionPrice();
        $payments           = SmsSubscriptionPayment::with('initiatedBy')->latest()->paginate(20);

        return view('send-sms.subscription', compact(
            'canSend', 'freeTrialRemaining', 'activeSubscription', 'subscriptionPrice', 'payments'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        $result = $this->subscription->subscribe($request->phone_number, $request->user());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
