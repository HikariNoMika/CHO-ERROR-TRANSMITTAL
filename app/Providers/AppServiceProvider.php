<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // Laravel's default paginator views are "pagination::tailwind" and
        // "pagination::simple-tailwind", but this app ships no Tailwind build,
        // so every paginator rendered unstyled. Point both at the hand-rolled
        // views in resources/views/vendor/pagination. The names must stay
        // namespaced: an unqualified "default" is looked up as a top-level
        // view and throws "View [default] not found".
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::simple');
    }
}