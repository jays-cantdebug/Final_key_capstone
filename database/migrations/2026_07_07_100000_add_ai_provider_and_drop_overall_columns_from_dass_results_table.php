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
     * `overall_status`/`overall_flag` were a single-combined-flag model
     * that never matched the approved schema; differentiated flagging now
     * evaluates each subscale independently (see flagged_cases). The
     * missing `ai_provider` column records which AI provider produced the
     * classification, as required.
     */
    public function up(): void
    {
        Schema::table('dass_results', function (Blueprint $table) {
            $table->string('ai_provider')->after('stress_level');
            $table->dropColumn(['overall_status', 'overall_flag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dass_results', function (Blueprint $table) {
            $table->dropColumn('ai_provider');
            $table->string('overall_status')->after('stress_level');
            $table->boolean('overall_flag')->default(false)->after('overall_status');
        });
    }
};
