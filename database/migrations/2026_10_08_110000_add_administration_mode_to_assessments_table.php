<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the questionnaire was answered: null for the same device (the
 * Psychometrician's own PC, as every assessment before this column was),
 * `student_device` for a separate student device.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('administration_mode', 20)->nullable()->after('privacy_consent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('administration_mode');
        });
    }
};
