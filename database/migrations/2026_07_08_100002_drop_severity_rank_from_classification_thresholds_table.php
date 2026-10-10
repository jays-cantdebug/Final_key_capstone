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
     * `severity_rank` is not part of the approved classification_thresholds
     * schema, and had zero remaining consumers after the AI rebuild — the
     * "highest severity" comparison it once supported now runs entirely in
     * PHP (see DassResult::highestSeverityLevel() and
     * RuleBasedDASSProvider), not against this column.
     */
    public function up(): void
    {
        Schema::table('classification_thresholds', function (Blueprint $table) {
            $table->dropColumn('severity_rank');
        });
    }

    /**
     * Reverse the migrations.
     *
     * default(0): SQLite can't add a NOT NULL column without a default to an
     * existing table, so a full rollback used to stop here (docs/BUG_LOG.md
     * N3). Nothing reads the column any more; restored rows get 0.
     */
    public function down(): void
    {
        Schema::table('classification_thresholds', function (Blueprint $table) {
            $table->unsignedTinyInteger('severity_rank')->default(0)->after('severity_level');
        });
    }
};
