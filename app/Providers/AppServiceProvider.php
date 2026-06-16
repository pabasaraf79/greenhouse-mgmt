<?php

namespace App\Providers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Greenhouse;
use App\Models\SensorReading;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Share sidebar + header data with the master layout.
        View::composer('layouts.app', function ($view) {
            $view->with('sidebarGreenhouses', Greenhouse::orderBy('name')->get());
            $view->with('sidebarAlertCount', Alert::where('status', 'active')->count());

            // A device is "live" if it has reported within the last 10 minutes.
            $devices = Device::get(['status', 'last_seen_at']);
            $cutoff = now()->subMinutes(10);
            $live = $devices->filter(fn ($d) => $d->last_seen_at && $d->last_seen_at->gt($cutoff))->count();
            $view->with('systemDeviceTotal', $devices->count());
            $view->with('systemDeviceLive', $live);

            // Most recent reading time across all devices (real "last synced").
            $last = SensorReading::max('recorded_at');
            $view->with('lastSyncedAt', $last ? Carbon::parse($last) : null);
        });
    }
}
