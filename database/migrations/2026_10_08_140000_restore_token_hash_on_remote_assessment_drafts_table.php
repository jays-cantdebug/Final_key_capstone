<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The link to the student PC (/s/t/{token}, copied from the live page) is
 * back, so its credential column is too. The 2026_10_08_130000 migration
 * that dropped it is left exactly as it is: it may already have run on
 * some database, and an applied migration is never edited or deleted.
 *
 * Guarded both ways with Schema::hasColumn, so it works whatever state a
 * database is in: up() adds the column only when it's missing (the drop
 * ran — on every database migrated in order, it has); down() removes it
 * whenever it's there, so a rollback always lands on the state after the
 * drop, and rolling back the drop as well brings the original column back.
 * Same definition as the original: char(64), nullable, unique (HMAC-SHA256
 * hex digests only; RemoteAssessmentService::hash()).
 *
 * The drop migration itself can't be guarded from here. It needs no guard
 * when migrations run in order; only a manual, out-of-order rollback of the
 * drop alone (`migrate:rollback --path=…130000…` while this one is still
 * applied) would fail, because its down() would add a column that exists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('remote_assessment_drafts', 'token_hash')) {
            return;
        }

        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->unique()->after('questionnaire_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('remote_assessment_drafts', 'token_hash')) {
            return;
        }

        // The unique index first (SQLite can't drop an indexed column); a
        // column added some other way may not have it.
        if (Schema::hasIndex('remote_assessment_drafts', ['token_hash'], 'unique')) {
            Schema::table('remote_assessment_drafts', function (Blueprint $table) {
                $table->dropUnique(['token_hash']);
            });
        }

        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });
    }
};
