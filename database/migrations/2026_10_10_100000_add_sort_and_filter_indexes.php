<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes on the columns the lists sort and filter by (docs/BUG_LOG.md L2):
 * the assessment and counseling-session dates (dashboards, lists, reports),
 * the audit-log timestamp, the Students list order (`deleted_at` scope +
 * newest `created_at` first), and the student name columns. Additive only;
 * each index is created only when missing, and down() drops only what is
 * there, so the migration is safe on MySQL and SQLite in any state.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> table => [index name => columns] */
    private const INDEXES = [
        'assessments' => ['assessments_submitted_at_index' => ['submitted_at']],
        'counseling_sessions' => ['counseling_sessions_session_datetime_index' => ['session_datetime']],
        'audit_logs' => ['audit_logs_created_at_index' => ['created_at']],
        'students' => [
            'students_deleted_at_created_at_index' => ['deleted_at', 'created_at'],
            'students_last_name_first_name_index' => ['last_name', 'first_name'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
                foreach ($indexes as $name => $columns) {
                    if (! Schema::hasIndex($table, $name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
                foreach (array_keys($indexes) as $name) {
                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};
