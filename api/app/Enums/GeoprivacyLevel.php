<?php

namespace App\Enums;

enum GeoprivacyLevel: string
{
    case Inherit = 'inherit';
    case Exact = 'exact';
    case Obscured = 'obscured';
    case Hidden = 'hidden';
}
