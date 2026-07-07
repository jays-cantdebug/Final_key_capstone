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
     * Replaces the single-overall-flag model (one row per assessment,
     * carrying only a `highest_severity` string) with differentiated
     * flagging: an assessment may now have one flagged_cases row for a
     * Counseling Endorsement (severe stress) AND a separate row for an
     * Awareness Notification (severe depression/anxiety), independently.
     */
    public function up(): void
    {
        Schema::table('flagged_cases', function (Blueprint $table) {
            // MySQL needs the FK's supporting index dropped/re-added around
            // the unique-index swap below (it refuses to drop an index that
            // a foreign key constraint currently relies on).
            $table->dropForeign(['assessment_id']);
            $table->dropUnique(['assessment_id']);

            $table->string('flag_type')->after('assessment_id');
            $table->string('triggering_subscale')->after('flag_type');

            $table->dropColumn('highest_severity');

            $table->unique(['assessment_id', 'flag_type', 'triggering_subscale']);
            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flagged_cases', function (Blueprint $table) {
            $table->dropForeign(['assessment_id']);
            $table->dropUnique(['assessment_id', 'flag_type', 'triggering_subscale']);
            $table->dropColumn(['flag_type', 'triggering_subscale']);

            $table->string('highest_severity')->after('assessment_id');
            $table->unique('assessment_id');
            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
        });
    }
};
