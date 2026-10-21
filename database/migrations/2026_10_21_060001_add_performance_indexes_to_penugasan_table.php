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
        Schema::table('penugasan', function (Blueprint $table) {
            // High-Performance Covering Composite Index for 200M rows:
            // Allows instant index-only joins from protokol_id directly to kegiatan_id
            $table->index(['protokol_id', 'kegiatan_id'], 'idx_penugasan_proto_kegiatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penugasan', function (Blueprint $table) {
            $table->dropIndex('idx_penugasan_proto_kegiatan');
        });
    }
};
