<?php

namespace App\Providers;

use App\Services\AccountingService;
use App\Services\FixedDepositService;
use App\Services\LoanService;
use App\Services\SavingsService;
use App\Models\SystemSetting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccountingService::class);

        $this->app->singleton(LoanService::class, function ($app) {
            return new LoanService($app->make(AccountingService::class));
        });

        $this->app->singleton(SavingsService::class, function ($app) {
            return new SavingsService($app->make(AccountingService::class));
        });

        $this->app->singleton(FixedDepositService::class, function ($app) {
            return new FixedDepositService(
                $app->make(AccountingService::class),
                $app->make(SavingsService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();

        try {
            View::share('dp', (int) SystemSetting::get('decimal_places', 2));
        } catch (\Exception $e) {
            View::share('dp', 2);
        }

        // Apply mail from name/address from DB settings (overrides .env at runtime)
        try {
            $fromName    = SystemSetting::get('mail_from_name');
            $fromAddress = SystemSetting::get('mail_from_address');
            if ($fromName)    config(['mail.from.name'    => $fromName]);
            if ($fromAddress) config(['mail.from.address' => $fromAddress]);
        } catch (\Exception $e) {
            // DB not ready (e.g. during migrations) — keep .env values
        }
    }
}
