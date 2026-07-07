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
     * Soft deletes on `users` is not in the approved soft-delete list —
     * user accounts are deactivated via `is_active`, never deleted or
     * restored. Confirmed no code path calls delete()/restore() on User.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
