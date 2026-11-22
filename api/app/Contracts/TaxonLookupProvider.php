<?php

namespace App\Contracts;

interface TaxonLookupProvider
{
    public function name(): string;

    /**
     * Search external taxonomic backbone for matching plant taxa.
     *
     * @return array<int, array{
     *   external_source: string,
     *   external_taxon_id: string,
     *   scientific_name: string,
     *   scientific_name_authorship: ?string,
     *   taxon_rank: string,
     *   kingdom: ?string,
     *   phylum: ?string,
     *   taxon_class: ?string,
     *   taxon_order: ?string,
     *   family: ?string,
     *   genus: ?string,
     *   specific_epithet: ?string,
     *   infraspecific_rank: ?string,
     *   infraspecific_epithet: ?string,
     *   common_names: array<int, array{name: string, language: string}>
     * }>
     */
    public function search(string $query, int $limit = 10): array;
}
