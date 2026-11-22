<?php

namespace App\Enums;

enum CurationStatus: string
{
    case Unmounted = 'unmounted';
    case Mounted = 'mounted';
    case Verified = 'verified';
    case Archived = 'archived';
    case Missing = 'missing';
}
