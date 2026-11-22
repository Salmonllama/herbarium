<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specimens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('herbarium_id')->constrained('herbaria')->cascadeOnDelete();
            $table->string('catalog_number', 100);
            $table->string('field_number', 100)->nullable()->index();
            $table->uuid('current_determination_id')->nullable()->index();

            // Workflow & visibility
            $table->string('visibility', 20)->default('draft')->index(); // draft, private, published
            $table->string('curation_status', 24)->default('unmounted')->index(); // unmounted, mounted, verified, archived, missing

            // Darwin Core field metadata
            $table->string('recorded_by', 255)->nullable();
            $table->jsonb('recorded_by_ids')->nullable();
            $table->date('event_date')->nullable()->index();
            $table->date('event_date_end')->nullable();
            $table->string('verbatim_event_date', 120)->nullable();
            $table->text('habitat')->nullable();
            $table->string('substrate', 255)->nullable();
            $table->text('associated_taxa')->nullable();
            $table->string('reproductive_condition', 100)->nullable();
            $table->string('preparations', 120)->default('pressed sheet');
            $table->string('type_status', 64)->nullable()->index();
            $table->text('occurrence_remarks')->nullable();

            // Physical storage tracking
            $table->string('storage_room', 80)->nullable();
            $table->string('storage_cabinet', 80)->nullable();
            $table->string('storage_shelf', 80)->nullable();
            $table->string('storage_folder', 120)->nullable();
            $table->text('storage_notes')->nullable();

            // Location & Elevation
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country', 120)->nullable()->index();
            $table->string('state_province', 120)->nullable()->index();
            $table->string('county', 120)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->text('locality')->nullable();
            $table->integer('elevation_min_m')->nullable();
            $table->integer('elevation_max_m')->nullable();

            // Exact & Obscured Coordinates
            $table->decimal('decimal_latitude', 10, 7)->nullable();
            $table->decimal('decimal_longitude', 10, 7)->nullable();
            $table->integer('coordinate_uncertainty_m')->nullable();
            $table->string('geodetic_datum', 32)->default('WGS84');
            $table->string('geoprivacy', 20)->default('inherit'); // inherit, exact, obscured, hidden
            $table->decimal('obscured_latitude', 10, 7)->nullable();
            $table->decimal('obscured_longitude', 10, 7)->nullable();
            $table->integer('obscured_uncertainty_m')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['herbarium_id', 'visibility', 'created_at']);
        });

        Schema::create('determinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('specimen_id')->constrained('specimens')->cascadeOnDelete();
            $table->foreignUuid('taxon_id')->nullable()->constrained('taxa')->nullOnDelete();
            $table->string('identified_by', 255)->nullable();
            $table->foreignId('identified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_identified')->nullable();
            $table->string('identification_qualifier', 32)->nullable(); // cf., aff., s.l., s.str.
            $table->text('identification_remarks')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamps();

            $table->index(['specimen_id', 'is_current']);
        });

        Schema::table('specimens', function (Blueprint $table) {
            $table->foreign('current_determination_id')
                ->references('id')
                ->on('determinations')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE specimens ADD COLUMN geom geography(Point, 4326)');
            DB::statement('ALTER TABLE specimens ADD COLUMN obscured_geom geography(Point, 4326)');
            DB::statement('CREATE UNIQUE INDEX specimens_herbarium_catalog_unique ON specimens (herbarium_id, catalog_number) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX specimens_geom_gist_idx ON specimens USING GIST (geom)');
            DB::statement('CREATE INDEX specimens_obscured_geom_gist_idx ON specimens USING GIST (obscured_geom)');
            DB::statement('CREATE INDEX specimens_catalog_trgm_idx ON specimens USING GIN (catalog_number gin_trgm_ops)');
            DB::statement('CREATE INDEX specimens_recorded_by_trgm_idx ON specimens USING GIN (recorded_by gin_trgm_ops)');
            DB::statement('CREATE INDEX specimens_locality_trgm_idx ON specimens USING GIN (locality gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::table('specimens', function (Blueprint $table) {
            $table->dropForeign(['current_determination_id']);
        });
        Schema::dropIfExists('determinations');
        Schema::dropIfExists('specimens');
    }
};
