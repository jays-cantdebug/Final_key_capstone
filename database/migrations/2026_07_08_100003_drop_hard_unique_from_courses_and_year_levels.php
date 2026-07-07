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
     * `courses.course_code` and `year_levels.display_order` had a hard
     * MySQL UNIQUE constraint, which has no concept of soft deletes: an
     * archived (soft-deleted) row still physically occupies its value in
     * the index, so re-using a code/order after archiving throws a raw
     * SQLSTATE 1062 error at the database layer even though application
     * validation (Rule::unique()->whereNull('deleted_at')) correctly
     * allows it. MySQL has no partial/filtered unique index, and a
     * composite (column, deleted_at) index doesn't work either — MySQL
     * exempts any row with a NULL in an indexed column from the
     * uniqueness check entirely, which would let unlimited live
     * duplicates through, since every live row has deleted_at IS NULL.
     * So the DB-level constraint is dropped in favor of a plain index
     * (for lookup performance) and the already-corrected FormRequest
     * validation becomes the sole enforcer of uniqueness among active
     * records — the standard resolution for this conflict.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['course_code']);
            $table->index('course_code');
        });

        Schema::table('year_levels', function (Blueprint $table) {
            $table->dropUnique(['display_order']);
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['course_code']);
            $table->unique('course_code');
        });

        Schema::table('year_levels', function (Blueprint $table) {
            $table->dropIndex(['display_order']);
            $table->unique('display_order');
        });
    }
};
