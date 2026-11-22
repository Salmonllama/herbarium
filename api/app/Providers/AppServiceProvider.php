<?php

namespace App\Providers;

use App\Contracts\MediaUrlResolver;
use App\Contracts\PlanLimitsContract;
use App\Domain\Media\DefaultMediaUrlResolver;
use App\Domain\Quota\UnlimitedPlanLimits;
use App\Models\Herbarium;
use App\Models\Specimen;
use App\Models\Taxon;
use App\Policies\HerbariumPolicy;
use App\Policies\SpecimenPolicy;
use App\Policies\TaxonPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanLimitsContract::class, UnlimitedPlanLimits::class);
        $this->app->singleton(MediaUrlResolver::class, DefaultMediaUrlResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(Herbarium::class, HerbariumPolicy::class);
        Gate::policy(Taxon::class, TaxonPolicy::class);
        Gate::policy(Specimen::class, SpecimenPolicy::class);
    }
}
