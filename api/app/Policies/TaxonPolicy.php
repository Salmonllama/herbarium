<?php

namespace App\Policies;

use App\Models\Herbarium;
use App\Models\Taxon;
use App\Models\User;

class TaxonPolicy
{
    public function view(?User $user, Taxon $taxon): bool
    {
        $herbarium = $taxon->herbarium;
        if ($herbarium->isPubliclyAccessible()) {
            return true;
        }

        return $user !== null && ($user->is_instance_admin || $user->membershipFor($herbarium) !== null);
    }

    public function create(User $user, Herbarium $herbarium): bool
    {
        return $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canCurate();
    }

    public function update(User $user, Taxon $taxon): bool
    {
        return $this->create($user, $taxon->herbarium);
    }

    public function delete(User $user, Taxon $taxon): bool
    {
        return $this->create($user, $taxon->herbarium);
    }
}
