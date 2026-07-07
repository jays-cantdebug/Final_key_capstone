<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `sex` and `status` are not part of the approved students schema —
     * `gender` already covers sex/gender (and is the field actually used
     * throughout the app), and `deleted_at` (soft delete) already serves
     * as the archive/active mechanism, making a separate `status` column
     * redundant and unsanctioned.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['sex', 'status']);
            $table->string('gender')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
            $table->string('sex')->after('last_name');
            $table->string('status')->default('Active')->after('section_id');
        });
    }
};
