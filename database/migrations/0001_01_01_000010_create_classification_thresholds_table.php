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
        Schema::create('classification_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('subscale');
            $table->string('severity_level');
            $table->unsignedTinyInteger('severity_rank');
            $table->unsignedInteger('min_score');
            $table->unsignedInteger('max_score');
            $table->timestamps();

            $table->unique(['subscale', 'severity_level']);
            $table->index('subscale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classification_thresholds');
    }
};
