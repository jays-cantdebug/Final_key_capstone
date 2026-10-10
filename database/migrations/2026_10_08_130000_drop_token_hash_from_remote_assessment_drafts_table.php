<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The student device is a PC provided by the guidance office, where the
 * student types the short code: the QR link (/s/t/{token}) was removed, so
 * its credential column goes too (data minimisation: no unused credential
 * column for a reviewer to wonder about). Drafts are temporary; a draft
 * live during the upgrade keeps working with its short code.
 *
 * The unique index is dropped before the column. down() puts both back,
 * empty (the old tokens are gone either way).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
        });

        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('remote_assessment_drafts', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->unique()->after('questionnaire_version_id');
        });
    }
};
