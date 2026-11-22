<?php

namespace App\Contracts;

use App\Models\Media;

interface MediaUrlResolver
{
    public function url(Media $media, string $variant = 'public'): ?string;
}
