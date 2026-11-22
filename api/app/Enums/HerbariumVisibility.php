<?php

namespace App\Enums;

enum HerbariumVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
}
