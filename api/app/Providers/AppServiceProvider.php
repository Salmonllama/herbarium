<?php

namespace App\Providers;

use App\Contracts\PlanLimitsContract;
use App\Domain\Quota\UnlimitedPlanLimits;
use App\Models\Herbarium;
use App\Policies\HerbariumPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanLimitsContract::class, UnlimitedPlanLimits::class);
    }

    public function boot(): void
    {
        Gate::policy(Herbarium::class, HerbariumPolicy::class);
    }
}
