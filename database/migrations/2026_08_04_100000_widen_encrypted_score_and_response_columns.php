<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the columns about to hold `encrypted`-cast Eloquent attributes
     * to TEXT: AES-256 ciphertext (base64-encoded iv/value/mac envelope)
     * runs several times longer than the plain integer it replaces and
     * will not fit an INTEGER/TINYINT column.
     */
    public function up(): void
    {
        Schema::table('dass_responses', function (Blueprint $table) {
            $table->text('answer_value')->change();
        });

        Schema::table('dass_results', function (Blueprint $table) {
            $table->text('depression_raw_score')->change();
            $table->text('anxiety_raw_score')->change();
            $table->text('stress_raw_score')->change();
            $table->text('depression_final_score')->change();
            $table->text('anxiety_final_score')->change();
            $table->text('stress_final_score')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dass_responses', function (Blueprint $table) {
            $table->unsignedTinyInteger('answer_value')->change();
        });

        Schema::table('dass_results', function (Blueprint $table) {
            $table->unsignedInteger('depression_raw_score')->change();
            $table->unsignedInteger('anxiety_raw_score')->change();
            $table->unsignedInteger('stress_raw_score')->change();
            $table->unsignedInteger('depression_final_score')->change();
            $table->unsignedInteger('anxiety_final_score')->change();
            $table->unsignedInteger('stress_final_score')->change();
        });
    }
};
