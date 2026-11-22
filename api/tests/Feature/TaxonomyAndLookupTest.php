<?php

namespace Tests\Feature;

use App\Enums\HerbariumRole;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxonomyAndLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_gbif_and_powo_taxonomy_lookup_endpoints(): void
    {
        Http::fake([
            'api.gbif.org/*' => Http::response([
                [
                    'key' => 2879737,
                    'canonicalName' => 'Quercus garryana',
                    'scientificName' => 'Quercus garryana Douglas ex Hook.',
                    'rank' => 'SPECIES',
                    'kingdom' => 'Plantae',
                    'phylum' => 'Tracheophyta',
                    'class' => 'Magnoliopsida',
                    'order' => 'Fagales',
                    'family' => 'Fagaceae',
                    'genus' => 'Quercus',
                ],
            ]),
            'powo.science.kew.org/*' => Http::response([
                'results' => [
                    [
                        'fqId' => 'urn:lsid:ipni.org:names:296228-1',
                        'name' => 'Pseudotsuga menziesii',
                        'author' => '(Mirb.) Franco',
                        'rank' => 'Species',
                        'family' => 'Pinaceae',
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/taxonomy/lookup?q=Quercus&source=gbif')
            ->assertOk()
            ->assertJsonPath('data.0.scientific_name', 'Quercus garryana')
            ->assertJsonPath('data.0.scientific_name_authorship', 'Douglas ex Hook.')
            ->assertJsonPath('data.0.family', 'Fagaceae')
            ->assertJsonPath('data.0.specific_epithet', 'garryana');

        $this->getJson('/api/v1/taxonomy/lookup?q=Pseudotsuga&source=powo')
            ->assertOk()
            ->assertJsonPath('data.0.scientific_name', 'Pseudotsuga menziesii')
            ->assertJsonPath('data.0.family', 'Pinaceae');
    }

    public function test_taxa_are_herbarium_scoped_and_produce_taxonomic_tree(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $h1 = Herbarium::create(['slug' => 'h1', 'name' => 'Herbarium One', 'created_by' => $owner->id]);
        $h2 = Herbarium::create(['slug' => 'h2', 'name' => 'Herbarium Two', 'created_by' => $owner->id]);

        HerbariumMembership::create(['herbarium_id' => $h1->id, 'user_id' => $owner->id, 'role' => HerbariumRole::Owner]);
        HerbariumMembership::create(['herbarium_id' => $h2->id, 'user_id' => $owner->id, 'role' => HerbariumRole::Owner]);

        $payload = [
            'scientific_name' => 'Quercus garryana',
            'scientific_name_authorship' => 'Douglas ex Hook.',
            'family' => 'Fagaceae',
            'genus' => 'Quercus',
            'specific_epithet' => 'garryana',
            'common_names' => [['name' => 'Oregon White Oak', 'language' => 'en']],
            'description_markdown' => 'Deciduous oak native to the Pacific Northwest.',
        ];

        $this->postJson('/api/v1/herbaria/h1/taxa', $payload)->assertCreated();
        // Duplicate in same herbarium fails
        $this->postJson('/api/v1/herbaria/h1/taxa', $payload)->assertStatus(422);
        // Same scientific name in a different herbarium succeeds (herbarium-scoped)
        $this->postJson('/api/v1/herbaria/h2/taxa', array_merge($payload, [
            'description_markdown' => 'Custom notes for Herbarium Two.',
        ]))->assertCreated();

        $this->getJson('/api/v1/herbaria/h1/taxonomy/tree')
            ->assertOk()
            ->assertJsonPath('data.0.family', 'Fagaceae')
            ->assertJsonPath('data.0.genera.0.genus', 'Quercus')
            ->assertJsonPath('data.0.genera.0.taxa.0.scientific_name', 'Quercus garryana');
    }
}
