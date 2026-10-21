<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // SEC-01: Hak Akses Khusus Admin Protokol & Super Admin untuk Master Data & Laporan
        Gate::define('manage-master', function (User $user) {
            return $user->isAdminProtokol();
        });
    }
}
