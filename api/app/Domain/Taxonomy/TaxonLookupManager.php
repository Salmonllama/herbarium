<?php

namespace App\Domain\Taxonomy;

use App\Contracts\TaxonLookupProvider;
use InvalidArgumentException;

class TaxonLookupManager
{
    /** @var array<string, TaxonLookupProvider> */
    private array $providers = [];

    public function __construct(
        GbifBackboneProvider $gbif,
        KewPowoProvider $powo,
    ) {
        $this->providers[$gbif->name()] = $gbif;
        $this->providers[$powo->name()] = $powo;
    }

    public function provider(string $source = 'gbif'): TaxonLookupProvider
    {
        $key = strtolower($source);
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("Unsupported taxonomy provider [{$source}].");
        }

        return $this->providers[$key];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $source = 'gbif', int $limit = 10): array
    {
        return $this->provider($source)->search($query, $limit);
    }
}
