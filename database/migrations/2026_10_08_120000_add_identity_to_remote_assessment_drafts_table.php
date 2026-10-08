<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student-device draft can also collect the student's own Step 1 details
 * (name, gender, course, year level, section), typed on the device after
 * the privacy notice. They are held only here, encrypted (see
 * RemoteAssessmentDraft), until the Psychometrician submits; the row is
 * deleted on submit, cancel and every other wizard exit, and pruned on
 * expiry. `held_at` is set when the typed name matches an active student:
 * the device then shows only the generic message.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->boolean('collects_identity')->default(false)->after('requires_consent');
            // Encrypted JSON (the Step 1 fields).
            $table->text('identity')->nullable()->after('collects_identity');
            $table->timestamp('identity_submitted_at')->nullable()->after('identity');
            $table->timestamp('identity_corrected_at')->nullable()->after('identity_submitted_at');
            $table->timestamp('held_at')->nullable()->after('identity_corrected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->dropColumn(['collects_identity', 'identity', 'identity_submitted_at', 'identity_corrected_at', 'held_at']);
        });
    }
};
