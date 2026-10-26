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
        // 1. Master Jenis Kegiatan: Soft Deletes, Flag Kunker & Composite Index
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->boolean('is_kunker')->default(false)->after('location_type');
            $table->softDeletes()->after('description');

            $table->index(['deleted_at'], 'idx_jenis_kegiatan_deleted');
        });

        // 2. Transaksional Kegiatan: Flag Kunker & High-Performance Index (200M scale)
        // WFA dikelola melalui enum PaymentDayType::WFA dan/atau evaluasi hari Jum'at non-libur
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->boolean('is_kunker')->default(false)->after('is_weekend');

            $table->index(['is_kunker', 'start_datetime'], 'idx_kegiatan_kunker_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropIndex('idx_kegiatan_kunker_start');
            $table->dropColumn('is_kunker');
        });

        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->dropIndex('idx_jenis_kegiatan_deleted');
            $table->dropColumn('is_kunker');
            $table->dropSoftDeletes();
        });
    }
};
