<?php

namespace App\Policies;

use App\Enums\SpecimenVisibility;
use App\Models\Herbarium;
use App\Models\Specimen;
use App\Models\User;

class SpecimenPolicy
{
    public function view(?User $user, Specimen $specimen): bool
    {
        $herbarium = $specimen->herbarium;

        if ($user && ($user->is_instance_admin || $user->roleIn($herbarium)?->canCurate())) {
            return true;
        }

        if ($user && $specimen->created_by === $user->id && $user->membershipFor($herbarium) !== null) {
            return true;
        }

        if ($specimen->visibility !== SpecimenVisibility::Published) {
            return false;
        }

        if ($herbarium->isPubliclyAccessible()) {
            return true;
        }

        return $user !== null && $user->membershipFor($herbarium) !== null;
    }

    public function create(User $user, Herbarium $herbarium): bool
    {
        return $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canContribute();
    }

    public function update(User $user, Specimen $specimen): bool
    {
        $herbarium = $specimen->herbarium;
        if ($user->is_instance_admin || $user->roleIn($herbarium)?->canCurate()) {
            return true;
        }

        // Contributors/Students can edit their own Draft specimens only
        return (bool) $user->roleIn($herbarium)?->canContribute()
            && $specimen->created_by === $user->id
            && $specimen->visibility === SpecimenVisibility::Draft;
    }

    public function publish(User $user, Specimen $specimen): bool
    {
        $herbarium = $specimen->herbarium;

        return $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canCurate();
    }

    public function delete(User $user, Specimen $specimen): bool
    {
        return $this->publish($user, $specimen);
    }

    public function determine(User $user, Specimen $specimen): bool
    {
        return $this->publish($user, $specimen);
    }
}
