<?php

namespace App\Jobs;

use App\Domain\Media\ImageDerivativeService;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMediaDerivatives implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $mediaId) {}

    public function handle(ImageDerivativeService $service): void
    {
        $media = Media::query()->find($this->mediaId);
        if (! $media) {
            return;
        }

        $service->process($media);
    }
}
