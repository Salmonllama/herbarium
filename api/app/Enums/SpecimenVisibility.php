<?php

namespace App\Enums;

enum SpecimenVisibility: string
{
    case Draft = 'draft';
    case Private = 'private';
    case Published = 'published';
}
