<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map()
    {
        $this->mapApiRoutes(); // Ensures API routes are loaded
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Ensure API routes are properly mapped.
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api') // API routes will have "/api" prefix
            ->middleware('api') // Uses API middleware
            ->namespace($this->namespace) // Ensures controllers are loaded correctly
            ->group(base_path('routes/api.php')); // Loads routes from api.php
    }
}
