<?php

namespace App\Providers;

use App\Contracts\ReceiptPrinter;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\WindowsEscPosReceiptPrinter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Midtrans\Config as MidtransConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReceiptPrinter::class, WindowsEscPosReceiptPrinter::class);
    }

    public function boot(): void
    {
        MidtransConfig::$serverKey = config('services.midtrans.server_key');
        MidtransConfig::$clientKey = config('services.midtrans.client_key');
        MidtransConfig::$isProduction = config('services.midtrans.is_production');
        MidtransConfig::$isSanitized = config('services.midtrans.sanitize');
        MidtransConfig::$is3ds = config('services.midtrans.3ds');

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        View::composer(['layouts.admin', 'auth.login', 'admin'], function ($view): void {
            $view->with('storeSettings', StoreSetting::current());

            if ($view->name() === 'layouts.admin' && auth()->user()?->role === 'admin') {
                $view->with('lowStockMenuCount', Product::query()->where('stock', '<', 10)->count());
            }
        });
    }
}
