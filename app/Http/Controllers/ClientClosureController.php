<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Surfaces clients with zero footprint in the system -- no savings account,
 * loan, fixed deposit, share, or client-tagged GL posting ever -- so staff
 * can review them and mark inactive. Distinct from Close Accounts (which
 * closes individual savings accounts with a zero balance); this is about
 * client records that were never actually used at all.
 */
class ClientClosureController extends Controller
{
    public function index(Request $request)
    {
        $clients = $this->eligibleQuery()
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('client_number', 'like', "%{$request->search}%"))
            ->with('segment', 'relationshipManager')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('client-closure.index', compact('clients'));
    }

    public function markInactive(Request $request)
    {
        $data = $request->validate([
            'client_ids'   => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
        ]);

        // Re-check eligibility at submit time rather than trusting the posted ids --
        // a client could have gained a product/transaction between page load and submit.
        $eligibleIds = $this->eligibleQuery()->whereIn('id', $data['client_ids'])->pluck('id');
        $skipped     = count($data['client_ids']) - $eligibleIds->count();

        $updated = Client::whereIn('id', $eligibleIds)->update(['status' => 'inactive']);

        $message = "{$updated} client(s) marked inactive.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (no longer eligible -- a product or transaction was added since this list loaded).";
        }

        return back()->with($skipped > 0 && $updated === 0 ? 'error' : 'success', $message);
    }

    private function eligibleQuery()
    {
        return Client::whereDoesntHave('savingsAccounts')
            ->whereDoesntHave('loans')
            ->whereDoesntHave('fixedDeposits')
            ->whereDoesntHave('shares')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transaction_lines')
                    ->whereColumn('transaction_lines.client_id', 'clients.id');
            });
    }
}
