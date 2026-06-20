<?php

namespace App\Providers;

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
        // When public/hot exists, @vite loads JS from the Vite dev server (port 5173). If that
        // server is not running, demo pages stay blank. Use built assets from public/build unless
        // VITE_USE_DEV_SERVER=true (with npm run dev). See .env.example.
        if (! filter_var(env('VITE_USE_DEV_SERVER', false), FILTER_VALIDATE_BOOLEAN)) {
            $hot = public_path('hot');
            if (is_file($hot)) {
                @unlink($hot);
            }
        }
    }
}
