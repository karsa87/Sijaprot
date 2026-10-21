<?php

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
        Schema::table('protokols', function (Blueprint $table) {
            $table->softDeletes()->after('notes');
            // High-performance composite index for soft deletes and status (200M rows)
            $table->index(['deleted_at', 'status'], 'idx_protokols_deleted_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('protokols', function (Blueprint $table) {
            $table->dropIndex('idx_protokols_deleted_status');
            $table->dropSoftDeletes();
        });
    }
};
