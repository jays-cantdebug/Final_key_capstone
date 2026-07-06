<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dass_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained('assessments')->cascadeOnDelete();
            $table->unsignedInteger('depression_raw_score');
            $table->unsignedInteger('anxiety_raw_score');
            $table->unsignedInteger('stress_raw_score');
            $table->unsignedInteger('depression_final_score');
            $table->unsignedInteger('anxiety_final_score');
            $table->unsignedInteger('stress_final_score');
            $table->string('depression_level');
            $table->string('anxiety_level');
            $table->string('stress_level');
            $table->string('overall_status');
            $table->boolean('overall_flag')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dass_results');
    }
};
