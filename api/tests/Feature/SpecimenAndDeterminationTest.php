<?php

namespace Tests\Feature;

use App\Enums\HerbariumRole;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use App\Models\Taxon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecimenAndDeterminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configurable_catalog_numbers_skip_manual_overrides(): void
    {
        $curator = User::factory()->create();
        $herbarium = Herbarium::create([
            'slug' => 'casc',
            'name' => 'Cascade Herbarium',
            'id_prefix' => 'CASC',
            'id_separator' => '-',
            'id_padding' => 5,
            'id_next_sequence' => 1,
            'created_by' => $curator->id,
        ]);
        HerbariumMembership::create([
            'herbarium_id' => $herbarium->id,
            'user_id' => $curator->id,
            'role' => HerbariumRole::Owner,
            'can_view_exact_coordinates' => true,
        ]);

        Sanctum::actingAs($curator);

        // Pre-claim CASC-00002 via manual legacy override
        $this->postJson('/api/v1/herbaria/casc/specimens', [
            'catalog_number' => 'CASC-00002',
            'field_number' => 'LEG-99',
            'recorded_by' => 'A. Collector',
        ])->assertCreated()
            ->assertJsonPath('data.catalog_number', 'CASC-00002');

        // Auto-generated #1 -> CASC-00001
        $this->postJson('/api/v1/herbaria/casc/specimens', [
            'recorded_by' => 'B. Collector',
        ])->assertCreated()
            ->assertJsonPath('data.catalog_number', 'CASC-00001');

        // Auto-generated #2 -> skips CASC-00002 -> CASC-00003
        $this->postJson('/api/v1/herbaria/casc/specimens', [
            'recorded_by' => 'C. Collector',
        ])->assertCreated()
            ->assertJsonPath('data.catalog_number', 'CASC-00003');
    }

    public function test_student_draft_workflow_curator_publish_and_determination_history(): void
    {
        $curator = User::factory()->create(['name' => 'Dr. Curator']);
        $student = User::factory()->create(['name' => 'Field Student']);

        $herbarium = Herbarium::create([
            'slug' => 'pnw',
            'name' => 'PNW Flora',
            'visibility' => 'public',
            'id_prefix' => 'PNW',
            'id_padding' => 4,
            'created_by' => $curator->id,
        ]);
        HerbariumMembership::create([
            'herbarium_id' => $herbarium->id,
            'user_id' => $curator->id,
            'role' => HerbariumRole::Curator,
            'can_view_exact_coordinates' => true,
        ]);
        HerbariumMembership::create([
            'herbarium_id' => $herbarium->id,
            'user_id' => $student->id,
            'role' => HerbariumRole::Contributor,
            'can_view_exact_coordinates' => false,
        ]);

        $taxon1 = Taxon::create([
            'herbarium_id' => $herbarium->id,
            'scientific_name' => 'Polystichum munitum',
            'family' => 'Polypodiaceae',
            'genus' => 'Polystichum',
            'created_by' => $curator->id,
        ]);
        $taxon2 = Taxon::create([
            'herbarium_id' => $herbarium->id,
            'scientific_name' => 'Polystichum imbricans',
            'family' => 'Polypodiaceae',
            'genus' => 'Polystichum',
            'created_by' => $curator->id,
        ]);

        // Student creates specimen (even if trying to set visibility=published, forced to draft)
        Sanctum::actingAs($student);
        $created = $this->postJson('/api/v1/herbaria/pnw/specimens', [
            'visibility' => 'published',
            'taxon_id' => $taxon1->id,
            'recorded_by' => 'Field Student',
            'event_date' => '2026-06-14',
            'country' => 'United States',
            'state_province' => 'Oregon',
            'storage_cabinet' => 'CAB-04',
            'storage_folder' => 'Ferns-A',
        ])->assertCreated()
            ->assertJsonPath('data.visibility', 'draft')
            ->assertJsonPath('data.current_determination.taxon.scientific_name', 'Polystichum munitum');

        $catalog = $created->json('data.catalog_number');

        // Student cannot publish
        $this->postJson("/api/v1/herbaria/pnw/specimens/{$catalog}/publish")->assertForbidden();

        // Anonymous public visitor cannot see Draft
        auth()->forgetGuards();
        $this->getJson("/api/v1/herbaria/pnw/specimens/{$catalog}")->assertForbidden();

        // Curator re-determines specimen with qualifier and publishes it
        Sanctum::actingAs($curator);
        $this->postJson("/api/v1/herbaria/pnw/specimens/{$catalog}/determinations", [
            'taxon_id' => $taxon2->id,
            'identification_qualifier' => 'cf.',
            'identification_remarks' => 'Pinnae overlapping; likely imbricans.',
        ])->assertCreated()
            ->assertJsonPath('data.is_current', true)
            ->assertJsonPath('data.identification_qualifier', 'cf.');

        $this->postJson("/api/v1/herbaria/pnw/specimens/{$catalog}/publish")
            ->assertOk()
            ->assertJsonPath('data.visibility', 'published');

        // Determination history preserves both annotations
        $this->getJson("/api/v1/herbaria/pnw/specimens/{$catalog}/determinations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.taxon.scientific_name', 'Polystichum imbricans')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.taxon.scientific_name', 'Polystichum munitum')
            ->assertJsonPath('data.1.is_current', false);

        // Facets & public catalog now include the published specimen
        $this->getJson('/api/v1/herbaria/pnw/specimens/facets')
            ->assertOk()
            ->assertJsonPath('families.0.value', 'Polypodiaceae')
            ->assertJsonPath('countries.0.value', 'United States');
    }
}
