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
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_kegiatan_id')->constrained('jenis_kegiatan')->restrictOnDelete();
            $table->string('title');
            $table->text('detail')->nullable();
            $table->dateTimeTz('start_datetime');
            $table->dateTimeTz('end_datetime');
            $table->unsignedSmallInteger('jumlah_protokol')->default(1);
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_weekend')->default(false);
            $table->string('location_type', 20)->default('dalam_kota');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // High-Performance Composite Indexes (Built for 200M rows)
            // 1. Calendar monthly queries & overlap window lookups
            $table->index(['start_datetime', 'end_datetime', 'status'], 'idx_kegiatan_range_status');

            // 2. Status filtration with temporal ordering
            $table->index(['status', 'start_datetime'], 'idx_kegiatan_status_start');

            // 3. Category distribution analysis
            $table->index(['jenis_kegiatan_id', 'start_datetime'], 'idx_kegiatan_jenis_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
