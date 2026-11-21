<?php

namespace App\Providers;

use App\Models\Gudang;
use App\Models\Laporan;
use App\Models\Pengiriman;
use App\Models\Stok;
use App\Policies\GudangPolicy;
use App\Policies\LaporanPolicy;
use App\Policies\PengirimanPolicy;
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
        /*
         * Registrasi policy otorisasi wilayah, instansi, dan penugasan (PBI-07 / PSKE-18).
         */
        Gate::policy(Laporan::class, LaporanPolicy::class);
        Gate::policy(Gudang::class, GudangPolicy::class);
        Gate::policy(Stok::class, GudangPolicy::class);
        Gate::policy(Pengiriman::class, PengirimanPolicy::class);
    }
}
