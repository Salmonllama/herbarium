<?php

namespace App\Enums;

enum TaxonRank: string
{
    case Kingdom = 'kingdom';
    case Phylum = 'phylum';
    case ClassRank = 'class';
    case Order = 'order';
    case Family = 'family';
    case Genus = 'genus';
    case Species = 'species';
    case Subspecies = 'subspecies';
    case Variety = 'variety';
    case Form = 'form';
    case Hybrid = 'hybrid';
    case Unranked = 'unranked';
}
