<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('orcid_id', 64)->nullable()->unique()->after('email');
            $table->string('institution')->nullable()->after('orcid_id');
            $table->boolean('is_instance_admin')->default(false)->after('institution');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['orcid_id', 'institution', 'is_instance_admin']);
        });
    }
};
