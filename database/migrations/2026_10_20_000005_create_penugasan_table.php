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
        Schema::create('penugasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('protokol_id')->constrained('protokols')->restrictOnDelete();
            $table->dateTimeTz('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_system_assigned')->default(true);
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->decimal('score_snapshot', 6, 2)->nullable();
            $table->text('selection_reason')->nullable();
            $table->timestamps();

            // Constraint: Satu protokol tidak boleh didaftarkan ganda di 1 kegiatan yang sama
            $table->unique(['kegiatan_id', 'protokol_id'], 'uq_penugasan_kegiatan_protokol');

            // High-Performance Composite Indexes (Built for 200M rows)
            // 1. Finding a protocol's last assigned task instantly for gap checking
            $table->index(['protokol_id', 'assigned_at'], 'idx_penugasan_proto_assigned');
            
            // 2. Joining directly from kegiatan to assigned protocols
            $table->index(['kegiatan_id'], 'idx_penugasan_kegiatan');
            
            // 3. Fast audit lookup for manual overrides
            $table->index(['is_override', 'assigned_at'], 'idx_penugasan_override_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penugasan');
    }
};
