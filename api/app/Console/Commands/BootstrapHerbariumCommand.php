<?php

namespace App\Console\Commands;

use App\Domain\Specimens\CatalogNumberGenerator;
use App\Domain\Specimens\DeterminationService;
use App\Enums\CurationStatus;
use App\Enums\GeoprivacyLevel;
use App\Enums\HerbariumRole;
use App\Enums\HerbariumVisibility;
use App\Enums\SpecimenVisibility;
use App\Enums\TaxonRank;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use App\Models\Specimen;
use App\Models\Taxon;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BootstrapHerbariumCommand extends Command
{
    protected $signature = 'herbarium:bootstrap
        {--email= : Initial instance administrator email}
        {--name=Herbarium Admin : Initial administrator name}
        {--password= : Initial administrator password}
        {--herbarium= : Initial herbarium name}
        {--slug= : Initial herbarium slug}
        {--prefix=HERB : Initial specimen catalog ID prefix}
        {--institution-code= : Optional Index Herbariorum code}
        {--seed-demo : Seed a small Pacific Northwest demonstration collection}';

    protected $description = 'Idempotently bootstrap the initial administrator, default herbarium, and optional botanical demo collection.';

    public function handle(CatalogNumberGenerator $catalogGen, DeterminationService $determinations): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: 'admin@herbarium.local')));
        $name = (string) $this->option('name');
        $password = (string) ($this->option('password') ?: Str::random(16));

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'is_instance_admin' => true,
            ]);
            $this->info("Created administrator account: {$user->email}");
        } else {
            if (! $user->is_instance_admin) {
                $user->update(['is_instance_admin' => true]);
            }
            $this->info("Administrator account already exists: {$user->email}");
        }

        $herbariumName = $this->option('herbarium') ?: 'Pacific Northwest Digital Herbarium';
        $slug = $this->option('slug') ?: Str::slug($herbariumName);
        $prefix = strtoupper((string) ($this->option('prefix') ?: 'HERB'));
        $instCode = $this->option('institution-code') ?: null;

        $herbarium = Herbarium::query()->where('slug', $slug)->first();
        if (! $herbarium) {
            $herbarium = Herbarium::create([
                'slug' => $slug,
                'name' => $herbariumName,
                'institution_code' => $instCode,
                'description' => 'Open-access digital herbarium documenting regional vascular flora, pressed voucher specimens, and field observations.',
                'contact_email' => $user->email,
                'visibility' => HerbariumVisibility::Public,
                'id_prefix' => $prefix,
                'id_separator' => '-',
                'id_padding' => 6,
                'id_next_sequence' => 1,
                'default_geoprivacy' => GeoprivacyLevel::Exact,
                'default_license' => 'CC-BY-4.0',
                'created_by' => $user->id,
            ]);

            HerbariumMembership::create([
                'herbarium_id' => $herbarium->id,
                'user_id' => $user->id,
                'role' => HerbariumRole::Owner,
                'can_view_exact_coordinates' => true,
            ]);

            $this->info("Created herbarium [{$herbarium->name}] at /h/{$herbarium->slug} (prefix {$prefix}-)");
        } else {
            $this->info("Herbarium [{$herbarium->slug}] already exists.");
        }

        if ($this->option('seed-demo') && $herbarium->taxa()->count() === 0) {
            $this->seedDemoCollection($herbarium, $user, $catalogGen, $determinations);
            $this->info('Seeded demonstration botanical collection (5 taxa, 6 specimens).');
        }

        return self::SUCCESS;
    }

    private function seedDemoCollection(
        Herbarium $herbarium,
        User $user,
        CatalogNumberGenerator $catalogGen,
        DeterminationService $determinations,
    ): void {
        $taxaSpecs = [
            [
                'scientific_name' => 'Quercus garryana',
                'scientific_name_authorship' => 'Douglas ex Hook.',
                'family' => 'Fagaceae',
                'genus' => 'Quercus',
                'specific_epithet' => 'garryana',
                'kingdom' => 'Plantae',
                'phylum' => 'Tracheophyta',
                'taxon_class' => 'Magnoliopsida',
                'taxon_order' => 'Fagales',
                'common_names' => [['name' => 'Oregon White Oak', 'language' => 'en'], ['name' => 'Garry Oak', 'language' => 'en']],
                'description_markdown' => 'Drought-tolerant deciduous oak characteristic of Pacific Northwest savannas and dry woodlands. Leaves deeply lobed with rounded tips; acorns seated in shallow scaly cups.',
                'ecology_notes' => 'Dry rocky slopes, oak savannas, and valley margins from 0–1200 m.',
                'is_sensitive_location' => false,
            ],
            [
                'scientific_name' => 'Polystichum munitum',
                'scientific_name_authorship' => '(Kaulf.) C.Presl',
                'family' => 'Dryopteridaceae',
                'genus' => 'Polystichum',
                'specific_epithet' => 'munitum',
                'kingdom' => 'Plantae',
                'phylum' => 'Polypodiophyta',
                'taxon_class' => 'Polypodiopsida',
                'taxon_order' => 'Polypodiales',
                'common_names' => [['name' => 'Western Sword Fern', 'language' => 'en']],
                'description_markdown' => 'Evergreen tufted fern forming dense crowns of lance-shaped once-pinnate fronds with characteristic upward-pointing hilt-like lobes at each pinna base.',
                'ecology_notes' => 'Dominant understory fern of moist Douglas-fir and western hemlock forests.',
                'is_sensitive_location' => false,
            ],
            [
                'scientific_name' => 'Trillium ovatum',
                'scientific_name_authorship' => 'Pursh',
                'family' => 'Melanthiaceae',
                'genus' => 'Trillium',
                'specific_epithet' => 'ovatum',
                'kingdom' => 'Plantae',
                'phylum' => 'Tracheophyta',
                'taxon_class' => 'Liliopsida',
                'taxon_order' => 'Liliales',
                'common_names' => [['name' => 'Pacific Trillium', 'language' => 'en'], ['name' => 'Western Wake-robin', 'language' => 'en']],
                'description_markdown' => 'Spring ephemeral perennial bearing a whorl of three broad ovate bracts beneath a solitary three-petaled white flower that ages deep rose-pink after pollination.',
                'ecology_notes' => 'Rich humic soils in shaded riparian and old-growth conifer forests.',
                'is_sensitive_location' => false,
            ],
            [
                'scientific_name' => 'Cypripedium montanum',
                'scientific_name_authorship' => 'Douglas ex Lindl.',
                'family' => 'Orchidaceae',
                'genus' => 'Cypripedium',
                'specific_epithet' => 'montanum',
                'kingdom' => 'Plantae',
                'phylum' => 'Tracheophyta',
                'taxon_class' => 'Liliopsida',
                'taxon_order' => 'Asparagales',
                'common_names' => [['name' => "Mountain Lady's Slipper", 'language' => 'en']],
                'description_markdown' => 'Distinctive terrestrial orchid with twisted brownish-maroon sepals/petals and an inflated porcelain-white pouch lip veined with purple inside.',
                'ecology_notes' => 'Open montane conifer woods and calcareous meadows; sensitive to collection pressure.',
                'conservation_status' => 'NT',
                'is_sensitive_location' => true,
            ],
            [
                'scientific_name' => 'Mahonia aquifolium',
                'scientific_name_authorship' => '(Pursh) Nutt.',
                'family' => 'Berberidaceae',
                'genus' => 'Mahonia',
                'specific_epithet' => 'aquifolium',
                'kingdom' => 'Plantae',
                'phylum' => 'Tracheophyta',
                'taxon_class' => 'Magnoliopsida',
                'taxon_order' => 'Ranunculales',
                'common_names' => [['name' => 'Tall Oregon-grape', 'language' => 'en']],
                'description_markdown' => 'Evergreen shrub with glossy spiny-toothed pinnate leaves, dense racemes of bright yellow spring flowers, and glaucous dark blue berries.',
                'ecology_notes' => 'Forest edges, dry woodlands, and rocky outcrops across western North America.',
                'is_sensitive_location' => false,
            ],
        ];

        $taxa = [];
        foreach ($taxaSpecs as $spec) {
            $taxa[$spec['scientific_name']] = Taxon::create(array_merge($spec, [
                'herbarium_id' => $herbarium->id,
                'taxon_rank' => TaxonRank::Species,
                'external_source' => 'manual',
                'created_by' => $user->id,
            ]));
        }

        $specimenSpecs = [
            [
                'taxon' => 'Quercus garryana',
                'field_number' => 'PNW-2026-014',
                'recorded_by' => 'Dr. Elena Vance',
                'event_date' => '2026-05-18',
                'country' => 'United States',
                'country_code' => 'US',
                'state_province' => 'Oregon',
                'county' => 'Wasco',
                'locality' => 'Tom McCall Preserve, basalt bench overlooking Columbia River Gorge',
                'habitat' => 'Open oak-pine savanna on shallow basalt loam with Balsamorhiza sagittata',
                'reproductive_condition' => 'flowering',
                'elevation_min_m' => 235,
                'decimal_latitude' => 45.68214,
                'decimal_longitude' => -121.30042,
                'coordinate_uncertainty_m' => 12,
                'curation_status' => CurationStatus::Verified,
                'visibility' => SpecimenVisibility::Published,
                'storage_room' => 'Botany Wing 204',
                'storage_cabinet' => 'CAB-02',
                'storage_shelf' => 'S-04',
                'storage_folder' => 'Fagaceae-Quercus',
            ],
            [
                'taxon' => 'Polystichum munitum',
                'field_number' => 'PNW-2026-021',
                'recorded_by' => 'Marcus Chen',
                'event_date' => '2026-04-09',
                'country' => 'United States',
                'country_code' => 'US',
                'state_province' => 'Oregon',
                'county' => 'Multnomah',
                'locality' => 'Forest Park, Wildwood Trail 0.4 km north of Lower Macleay footbridge',
                'habitat' => 'Moist Pseudotsuga menziesii / Acer macrophyllum ravine',
                'reproductive_condition' => 'spore-bearing',
                'elevation_min_m' => 165,
                'decimal_latitude' => 45.53642,
                'decimal_longitude' => -122.71925,
                'coordinate_uncertainty_m' => 8,
                'curation_status' => CurationStatus::Mounted,
                'visibility' => SpecimenVisibility::Published,
                'storage_room' => 'Botany Wing 204',
                'storage_cabinet' => 'CAB-01',
                'storage_shelf' => 'S-01',
                'storage_folder' => 'Pteridophyta-Dryopteridaceae',
            ],
            [
                'taxon' => 'Trillium ovatum',
                'field_number' => 'PNW-2026-008',
                'recorded_by' => 'Dr. Elena Vance',
                'event_date' => '2026-03-27',
                'country' => 'United States',
                'country_code' => 'US',
                'state_province' => 'Washington',
                'county' => 'Skamania',
                'locality' => 'Beacon Rock State Park, shaded creek terrace near Hardy Creek',
                'habitat' => 'Old-growth Thuja plicata duff beside perennial stream',
                'reproductive_condition' => 'flowering',
                'elevation_min_m' => 92,
                'decimal_latitude' => 45.63110,
                'decimal_longitude' => -122.02180,
                'coordinate_uncertainty_m' => 15,
                'curation_status' => CurationStatus::Verified,
                'visibility' => SpecimenVisibility::Published,
                'storage_room' => 'Botany Wing 204',
                'storage_cabinet' => 'CAB-05',
                'storage_shelf' => 'S-02',
                'storage_folder' => 'Melanthiaceae-Trillium',
            ],
            [
                'taxon' => 'Cypripedium montanum',
                'field_number' => 'PNW-2026-044',
                'recorded_by' => 'Dr. Elena Vance',
                'event_date' => '2026-06-03',
                'country' => 'United States',
                'country_code' => 'US',
                'state_province' => 'Oregon',
                'county' => 'Hood River',
                'locality' => 'East slope mixed pine-fir glade near Surveyor Ridge (exact site protected)',
                'habitat' => 'Dappled Pinus ponderosa understory on calcareous loam',
                'reproductive_condition' => 'flowering',
                'elevation_min_m' => 1140,
                'decimal_latitude' => 45.51280,
                'decimal_longitude' => -121.54190,
                'coordinate_uncertainty_m' => 10,
                'geoprivacy' => GeoprivacyLevel::Inherit, // Inherited sensitive taxon -> automatically obscured publicly!
                'curation_status' => CurationStatus::Verified,
                'visibility' => SpecimenVisibility::Published,
                'storage_room' => 'Botany Wing 204',
                'storage_cabinet' => 'CAB-09',
                'storage_shelf' => 'S-01',
                'storage_folder' => 'Orchidaceae-Sensitive',
            ],
            [
                'taxon' => 'Mahonia aquifolium',
                'field_number' => 'PNW-2026-032',
                'recorded_by' => 'Avery Lindholm',
                'event_date' => '2026-04-22',
                'country' => 'United States',
                'country_code' => 'US',
                'state_province' => 'Oregon',
                'county' => 'Benton',
                'locality' => 'McDonald-Dunn Research Forest, Peavy Arboretum south slope',
                'habitat' => 'Sunlit Douglas-fir woodland edge on clay loam',
                'reproductive_condition' => 'flowering',
                'elevation_min_m' => 195,
                'decimal_latitude' => 44.65792,
                'decimal_longitude' => -123.23714,
                'coordinate_uncertainty_m' => 20,
                'curation_status' => CurationStatus::Mounted,
                'visibility' => SpecimenVisibility::Published,
                'storage_room' => 'Botany Wing 204',
                'storage_cabinet' => 'CAB-03',
                'storage_shelf' => 'S-03',
                'storage_folder' => 'Berberidaceae-Mahonia',
            ],
            [
                'taxon' => 'Quercus garryana',
                'field_number' => 'PNW-2026-051',
                'recorded_by' => 'Avery Lindholm',
                'event_date' => '2026-07-02',
                'country' => 'United States',
                'country_code' => 'US',
                'state_province' => 'Washington',
                'county' => 'Klickitat',
                'locality' => 'Catherine Creek universal trail oak woodland',
                'habitat' => 'South-facing dry basalt slope with maturing acorns',
                'reproductive_condition' => 'fruiting',
                'elevation_min_m' => 180,
                'decimal_latitude' => 45.71024,
                'decimal_longitude' => -121.36385,
                'coordinate_uncertainty_m' => 18,
                'curation_status' => CurationStatus::Unmounted,
                'visibility' => SpecimenVisibility::Draft,
                'storage_room' => 'Botany Wing 204',
                'storage_cabinet' => 'INTAKE',
                'storage_shelf' => 'T-01',
                'storage_folder' => '2026-Student-Drafts',
            ],
        ];

        foreach ($specimenSpecs as $spec) {
            $taxonName = $spec['taxon'];
            unset($spec['taxon']);

            $specimen = Specimen::create(array_merge($spec, [
                'herbarium_id' => $herbarium->id,
                'catalog_number' => $catalogGen->nextFor($herbarium),
                'preparations' => 'pressed sheet',
                'geodetic_datum' => 'WGS84',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));

            $determinations->record($specimen, [
                'taxon_id' => $taxa[$taxonName]->id,
                'identified_by' => $spec['recorded_by'],
                'date_identified' => $spec['event_date'],
            ], $user);
        }
    }
}
