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
     * Records whether this specific assessment was classified while
     * non-official (overridden) classification_thresholds were in
     * effect. Overriding thresholds never retroactively reclassifies
     * past assessments, so this must be captured at scoring time — there
     * is no way to reconstruct it after the fact once thresholds are
     * later restored or changed again.
     */
    public function up(): void
    {
        Schema::table('dass_results', function (Blueprint $table) {
            $table->boolean('used_non_official_thresholds')->default(false)->after('ai_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dass_results', function (Blueprint $table) {
            $table->dropColumn('used_non_official_thresholds');
        });
    }
};
