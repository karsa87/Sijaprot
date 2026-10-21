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
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->softDeletes()->after('created_by');

            // High-Performance Composite Index for 200M rows
            // Optimizes all queries filtering out soft-deleted activities within calendar ranges
            $table->index(['deleted_at', 'start_datetime', 'status'], 'idx_kegiatan_deleted_start_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropIndex('idx_kegiatan_deleted_start_status');
            $table->dropSoftDeletes();
        });
    }
};
