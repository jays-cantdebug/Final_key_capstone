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
        Schema::create('dass_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_version_id')->constrained('questionnaire_versions')->restrictOnDelete();
            $table->unsignedInteger('item_number');
            $table->text('question_text');
            $table->string('question_type');
            $table->string('subscale');
            $table->unsignedInteger('display_order');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['questionnaire_version_id', 'item_number']);
            $table->unique(['questionnaire_version_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dass_questions');
    }
};
