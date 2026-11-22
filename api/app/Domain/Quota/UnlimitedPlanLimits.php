<?php

namespace App\Domain\Quota;

use App\Contracts\PlanLimitsContract;
use App\Models\Herbarium;
use App\Models\User;

class UnlimitedPlanLimits implements PlanLimitsContract
{
    public function canCreateHerbarium(User $user): bool
    {
        return true;
    }

    public function canCreateSpecimen(Herbarium $herbarium): bool
    {
        return true;
    }

    public function canUploadMedia(Herbarium $herbarium, int $sizeBytes): bool
    {
        return true;
    }

    public function canUseCustomDomain(Herbarium $herbarium): bool
    {
        return true;
    }

    public function describeLimits(Herbarium $herbarium): array
    {
        return [
            'edition' => 'community',
            'max_specimens' => null,
            'max_storage_bytes' => null,
            'custom_domain_enabled' => true,
        ];
    }
}
