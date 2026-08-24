<?php

namespace App\Http\Controllers;

use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;

class SmsSubscriptionController extends Controller
{
    public function __construct(protected SmsSubscriptionService $subscription) {}

    public function store(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        $result = $this->subscription->subscribe($request->phone_number, $request->user());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
