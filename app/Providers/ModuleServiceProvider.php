<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModuleServiceProvider extends ServiceProvider
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
        $modulesPath = app_path('Modules');

        if (!file_exists($modulesPath)) {
            return;
        }

        $modules = array_filter(glob($modulesPath.'/*'), 'is_dir');

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $moduleNamespace = 'App\\Modules\\'.$moduleName;

            // 1. Load Routes
            $webRoute = $modulePath.'/Routes/web.php';
            if (file_exists($webRoute)) {
                Route::middleware('web')
                    ->namespace($moduleNamespace.'\\Controllers')
                    ->group($webRoute);
            }

            $apiRoute = $modulePath.'/Routes/api.php';
            if (file_exists($apiRoute)) {
                Route::middleware('api')
                    ->prefix('api')
                    ->namespace($moduleNamespace.'\\Controllers')
                    ->group($apiRoute);
            }

            // 2. Load Views
            $viewsPath = $modulePath.'/Views';
            if (file_exists($viewsPath)) {
                $this->loadViewsFrom($viewsPath, Str::lower($moduleName));
            }

            // 3. Load Migrations
            $migrationsPath = $modulePath.'/Database/Migrations';
            if (file_exists($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
}
