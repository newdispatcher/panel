<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\ServiceProvider;
use Pterodactyl\Services\Addons\CurseForgeService;
use Pterodactyl\Services\Addons\ModrinthService;
use Pterodactyl\Services\Addons\SpigotMCService;
use Pterodactyl\Services\Addons\WorldManagerService;

class AddonServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CurseForgeService::class);
        $this->app->singleton(ModrinthService::class);
        $this->app->singleton(SpigotMCService::class);
        $this->app->singleton(WorldManagerService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}