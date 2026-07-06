<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive change only: adds the `gender` and `privacy_consent_at`
     * columns required by the New Assessment workflow. The legacy `sex`
     * column is left in place, untouched, and unused going forward.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('sex');
            $table->timestamp('privacy_consent_at')->nullable()->after('status');
        });

        DB::table('students')->select('id', 'sex')->orderBy('id')->chunkById(100, function ($students) {
            foreach ($students as $student) {
                DB::table('students')->where('id', $student->id)->update(['gender' => $student->sex]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['gender', 'privacy_consent_at']);
        });
    }
};
