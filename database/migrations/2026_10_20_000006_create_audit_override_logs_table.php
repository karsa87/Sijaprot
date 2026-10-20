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
        Schema::create('audit_override_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penugasan_id')->nullable()->constrained('penugasan')->nullOnDelete();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('previous_protokol_id')->nullable()->constrained('protokols')->nullOnDelete();
            $table->foreignId('new_protokol_id')->nullable()->constrained('protokols')->nullOnDelete();
            $table->foreignId('overridden_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();

            // High-performance audit indexes
            $table->index(['kegiatan_id', 'created_at'], 'idx_audit_kegiatan_time');
            $table->index(['overridden_by', 'created_at'], 'idx_audit_user_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_override_logs');
    }
};
