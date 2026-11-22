<?php

namespace App\Policies;

use App\Models\Herbarium;
use App\Models\User;

class HerbariumPolicy
{
    public function view(?User $user, Herbarium $herbarium): bool
    {
        if ($herbarium->isPubliclyAccessible()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->is_instance_admin || $user->membershipFor($herbarium) !== null;
    }

    public function update(User $user, Herbarium $herbarium): bool
    {
        return $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canManageHerbarium();
    }

    public function delete(User $user, Herbarium $herbarium): bool
    {
        return $this->update($user, $herbarium);
    }

    public function manageMembers(User $user, Herbarium $herbarium): bool
    {
        return $this->update($user, $herbarium);
    }

    public function curate(User $user, Herbarium $herbarium): bool
    {
        return $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canCurate();
    }

    public function contribute(User $user, Herbarium $herbarium): bool
    {
        return $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canContribute();
    }
}
