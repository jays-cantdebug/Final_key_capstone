<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary drafts for a questionnaire answered on a separate student
 * device. A draft holds no student data — no student id, name, IP or
 * user agent — only the answers (encrypted, see RemoteAssessmentDraft),
 * hashes of its credentials, and timestamps. Rows are deleted when the
 * Psychometrician submits or cancels, and pruned once `expires_at` passes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('remote_assessment_drafts', function (Blueprint $table) {
            $table->id();
            // Unique: a Psychometrician has at most one live draft.
            $table->foreignId('psychometrician_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('questionnaire_version_id')->constrained('questionnaire_versions');
            // HMAC-SHA256 hex digests (RemoteAssessmentService::hash()); the
            // plain values are never stored here.
            $table->char('token_hash', 64)->nullable()->unique();
            $table->char('short_code_hash', 64)->nullable()->unique();
            $table->char('device_hash', 64)->nullable()->index();
            $table->string('status', 20);
            $table->boolean('requires_consent');
            $table->timestamp('consented_at')->nullable();
            // Encrypted JSON (question id => answer value).
            $table->text('responses')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedSmallInteger('refused_device_attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remote_assessment_drafts');
    }
};
