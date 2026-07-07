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
     * Feedback Loop: lets the Psychometrician confirm or correct the AI's
     * classification. One row per assessment, updated in place on
     * subsequent edits (see PredictionFeedbackService).
     */
    public function up(): void
    {
        Schema::create('prediction_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('psychometrician_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_confirmed')->default(false);
            $table->string('corrected_depression_level')->nullable();
            $table->string('corrected_anxiety_level')->nullable();
            $table->string('corrected_stress_level')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediction_feedback');
    }
};
