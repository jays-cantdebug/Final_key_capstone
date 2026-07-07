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
     * A single assessment can now generate two independent flagged cases
     * (Counseling Endorsement + Awareness Notification) for the same
     * Guidance Counselor, so notifications must be keyed to the specific
     * flagged_case, not just the assessment.
     */
    public function up(): void
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            // MySQL needs the FKs' supporting index dropped/re-added around
            // the unique-index swap below (it refuses to drop an index that
            // a foreign key constraint currently relies on).
            $table->dropForeign(['user_id']);
            $table->dropForeign(['assessment_id']);
            $table->dropUnique(['user_id', 'assessment_id']);

            $table->foreignId('flagged_case_id')->after('assessment_id')->constrained('flagged_cases')->cascadeOnDelete();
            $table->string('notification_type')->after('flagged_case_id');

            $table->unique(['user_id', 'flagged_case_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['assessment_id']);
            $table->dropUnique(['user_id', 'flagged_case_id']);
            $table->dropConstrainedForeignId('flagged_case_id');
            $table->dropColumn('notification_type');

            $table->unique(['user_id', 'assessment_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
        });
    }
};
