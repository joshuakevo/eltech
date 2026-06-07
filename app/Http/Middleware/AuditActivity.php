<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class AuditActivity
{
    // Routes to skip (noise / polling)
    private const SKIP_ROUTES = [
        'backup.*', 'settings.*',
    ];

    // Map route-name prefixes → human labels
    private const MODULE_MAP = [
        'clients'         => 'Clients',
        'loans'           => 'Loans',
        'loan-products'   => 'Loan Products',
        'savings'         => 'Savings',
        'savings-products'=> 'Savings Products',
        'fixed-deposits'  => 'Fixed Deposits',
        'fd-products'     => 'FD Products',
        'transactions'    => 'Journal Entries',
        'accounts'        => 'Chart of Accounts',
        'reports'         => 'Reports',
        'users'           => 'Users',
        'employees'       => 'HR',
        'payroll'         => 'Payroll',
        'branches'        => 'Branches',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log mutating requests from authenticated users
        if (!auth()->check() || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $response;
        }

        // Skip failed validation redirects (no real action taken)
        if ($response->isRedirect() && session()->has('errors') && session('errors')->any()) {
            return $response;
        }

        $routeName = $request->route()?->getName() ?? '';

        // Resolve module from route name prefix
        $module = null;
        foreach (self::MODULE_MAP as $prefix => $label) {
            if (str_starts_with($routeName, $prefix)) {
                $module = $label;
                break;
            }
        }

        // Build description from method + route
        $event       = $this->methodToEvent($request->method());
        $description = $this->buildDescription($request, $routeName);

        AuditLog::record($event, $description, $module);

        return $response;
    }

    private function methodToEvent(string $method): string
    {
        return match($method) {
            'POST'   => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default  => 'action',
        };
    }

    private function buildDescription(Request $request, string $routeName): string
    {
        $method = $request->method();
        $path   = $request->path();

        // Friendly labels for known routes
        $labels = [
            'clients.store'              => 'Created a new client',
            'clients.update'             => 'Updated client',
            'clients.destroy'            => 'Deleted client',
            'clients.membership-payment' => 'Recorded membership fee payment',
            'clients.shares.payment'     => 'Recorded share payment',
            'clients.shares.add'         => 'Added share subscription',
            'loans.store'                => 'Created new loan',
            'loans.disburse'             => 'Disbursed loan',
            'loans.repay'                => 'Recorded loan repayment',
            'loans.destroy'              => 'Deleted loan',
            'savings.deposit'            => 'Savings deposit',
            'savings.withdraw'           => 'Savings withdrawal',
            'savings.transfer'           => 'Savings transfer',
            'savings.store'              => 'Opened savings account',
            'fixed-deposits.store'       => 'Created fixed deposit',
            'fixed-deposits.payout'      => 'Paid out fixed deposit',
            'fixed-deposits.break'       => 'Broke fixed deposit',
            'transactions.store'         => 'Posted manual journal entry',
            'transactions.destroy'       => 'Deleted journal entry',
            'transactions.reverse'       => 'Reversed journal entry',
            'payroll.pay'                => 'Processed payroll payment',
            'users.store'                => 'Created new user',
            'users.update'               => 'Updated user',
            'users.destroy'              => 'Deleted user',
            'settings.update'            => 'Updated system settings',
            'settings.reconcile'         => 'Ran data reconciliation',
        ];

        return $labels[$routeName] ?? "{$method} /{$path}";
    }
}
