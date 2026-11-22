<?php

namespace Tests\Feature;

use App\Enums\GeoprivacyLevel;
use App\Enums\HerbariumRole;
use App\Enums\SpecimenVisibility;
use App\Models\Determination;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use App\Models\Specimen;
use App\Models\Taxon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeoprivacyAndMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_taxon_obscures_public_coordinates_and_blocks_bbox_probing(): void
    {
        $curator = User::factory()->create();
        $herbarium = Herbarium::create([
            'slug' => 'rare-flora',
            'name' => 'Rare Flora Herbarium',
            'visibility' => 'public',
            'created_by' => $curator->id,
        ]);
        HerbariumMembership::create([
            'herbarium_id' => $herbarium->id,
            'user_id' => $curator->id,
            'role' => HerbariumRole::Owner,
            'can_view_exact_coordinates' => true,
        ]);

        $rareOrchid = Taxon::create([
            'herbarium_id' => $herbarium->id,
            'scientific_name' => 'Cypripedium montanum',
            'family' => 'Orchidaceae',
            'genus' => 'Cypripedium',
            'is_sensitive_location' => true,
            'created_by' => $curator->id,
        ]);

        $specimen = Specimen::create([
            'herbarium_id' => $herbarium->id,
            'catalog_number' => 'RARE-00001',
            'visibility' => SpecimenVisibility::Published,
            'geoprivacy' => GeoprivacyLevel::Inherit,
            'country' => 'United States',
            'state_province' => 'Oregon',
            'county' => 'Hood River',
            'locality' => 'Secret hollow 120m behind fallen cedar',
            'decimal_latitude' => 45.501000,
            'decimal_longitude' => -121.601000,
            'coordinate_uncertainty_m' => 15,
            'created_by' => $curator->id,
        ]);

        $det = Determination::create([
            'specimen_id' => $specimen->id,
            'taxon_id' => $rareOrchid->id,
            'identified_by' => 'Dr. Botanist',
            'is_current' => true,
        ]);
        $specimen->update(['current_determination_id' => $det->id]);
        $specimen->refresh();

        // Also create a Hidden specimen
        Specimen::create([
            'herbarium_id' => $herbarium->id,
            'catalog_number' => 'RARE-00002',
            'visibility' => SpecimenVisibility::Published,
            'geoprivacy' => GeoprivacyLevel::Hidden,
            'country' => 'United States',
            'locality' => 'Private landowner property',
            'decimal_latitude' => 45.550000,
            'decimal_longitude' => -121.650000,
            'created_by' => $curator->id,
        ]);

        // Public visitor receives obscured coords, redacted locality, and deterministic repeat result
        $pub1 = $this->getJson('/api/v1/herbaria/rare-flora/specimens/RARE-00001')
            ->assertOk()
            ->assertJsonPath('data.location.effective_geoprivacy', 'obscured')
            ->assertJsonPath('data.location.coordinates_obscured', true)
            ->assertJsonPath('data.location.locality', null);

        $pub2 = $this->getJson('/api/v1/herbaria/rare-flora/specimens/RARE-00001')->assertOk();

        $this->assertEquals($pub1->json('data.location.decimal_latitude'), $pub2->json('data.location.decimal_latitude'));
        $this->assertNotEquals(45.501, $pub1->json('data.location.decimal_latitude'));
        $this->assertGreaterThanOrEqual(10000, $pub1->json('data.location.coordinate_uncertainty_m'));

        // Hidden specimen returns null coords & null locality publicly
        $this->getJson('/api/v1/herbaria/rare-flora/specimens/RARE-00002')
            ->assertOk()
            ->assertJsonPath('data.location.effective_geoprivacy', 'hidden')
            ->assertJsonPath('data.location.decimal_latitude', null)
            ->assertJsonPath('data.location.locality', null);

        // Anti-probing test: tight bbox around exact point (excluding obscured point) MUST NOT match publicly
        $tightExactBbox = sprintf('%f,%f,%f,%f', -121.602, 45.500, -121.600, 45.502);
        $this->getJson("/api/v1/herbaria/rare-flora/map/points?bbox={$tightExactBbox}")
            ->assertOk()
            ->assertJsonPath('count', 0);

        // World bbox publicly returns RARE-00001 (obscured) and NEVER returns RARE-00002 (hidden)
        $worldMap = $this->getJson('/api/v1/herbaria/rare-flora/map/points?bbox=-180,-85,180,85')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('features.0.properties.catalog_number', 'RARE-00001')
            ->assertJsonPath('features.0.properties.coordinates_obscured', true);

        $this->assertEquals($specimen->obscured_latitude, $worldMap->json('features.0.geometry.coordinates.1'));

        // Curator sees exact coordinates and both specimens on map
        Sanctum::actingAs($curator);
        $this->getJson('/api/v1/herbaria/rare-flora/specimens/RARE-00001')
            ->assertOk()
            ->assertJsonPath('data.location.coordinates_obscured', false)
            ->assertJsonPath('data.location.decimal_latitude', 45.501)
            ->assertJsonPath('data.location.locality', 'Secret hollow 120m behind fallen cedar');

        $this->getJson('/api/v1/herbaria/rare-flora/map/points?bbox=-180,-85,180,85')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }
}
