<?php

namespace App\Enums;

enum HerbariumRole: string
{
    case Owner = 'owner';
    case Curator = 'curator';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    public function canManageHerbarium(): bool
    {
        return $this === self::Owner;
    }

    public function canCurate(): bool
    {
        return in_array($this, [self::Owner, self::Curator], true);
    }

    public function canContribute(): bool
    {
        return in_array($this, [self::Owner, self::Curator, self::Contributor], true);
    }

    public function defaultExactCoordinates(): bool
    {
        return in_array($this, [self::Owner, self::Curator], true);
    }
}
