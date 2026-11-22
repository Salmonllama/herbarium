<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herbarium_id')->constrained('herbaria')->cascadeOnDelete();
            $table->string('scientific_name', 255);
            $table->string('scientific_name_authorship', 255)->nullable();
            $table->string('taxon_rank', 32)->default('species');
            $table->string('kingdom', 100)->nullable();
            $table->string('phylum', 100)->nullable();
            $table->string('taxon_class', 100)->nullable();
            $table->string('taxon_order', 100)->nullable();
            $table->string('family', 100)->nullable()->index();
            $table->string('genus', 100)->nullable()->index();
            $table->string('specific_epithet', 100)->nullable();
            $table->string('infraspecific_rank', 32)->nullable();
            $table->string('infraspecific_epithet', 100)->nullable();
            $table->jsonb('common_names')->nullable();
            $table->text('description_markdown')->nullable();
            $table->text('morphology_notes')->nullable();
            $table->text('ecology_notes')->nullable();
            $table->string('conservation_status', 32)->nullable();
            $table->string('external_source', 32)->default('manual'); // gbif, powo, manual
            $table->string('external_taxon_id', 128)->nullable();
            $table->timestamp('external_synced_at')->nullable();
            $table->boolean('is_sensitive_location')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['herbarium_id', 'family', 'genus']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX taxa_herbarium_scientific_name_unique ON taxa (herbarium_id, LOWER(scientific_name)) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX taxa_scientific_name_trgm_idx ON taxa USING GIN (scientific_name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxa');
    }
};
