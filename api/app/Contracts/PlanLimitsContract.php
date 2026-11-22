<?php

namespace App\Contracts;

use App\Models\Herbarium;
use App\Models\User;

/**
 * Extension hook allowing hosted cloud deployments to enforce plan quotas
 * (e.g. max herbaria, max specimens, storage bytes, custom domains) without
 * modifying the open-source core.
 */
interface PlanLimitsContract
{
    public function canCreateHerbarium(User $user): bool;

    public function canCreateSpecimen(Herbarium $herbarium): bool;

    public function canUploadMedia(Herbarium $herbarium, int $sizeBytes): bool;

    public function canUseCustomDomain(Herbarium $herbarium): bool;

    /**
     * @return array<string, int|bool|null>
     */
    public function describeLimits(Herbarium $herbarium): array;
}
