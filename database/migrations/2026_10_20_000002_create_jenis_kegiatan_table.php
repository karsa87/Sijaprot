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
        Schema::create('jenis_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('payment_type', 20)->default('unpaid');
            $table->string('payment_day_type', 20)->nullable();
            $table->string('location_type', 20)->default('dalam_kota');
            $table->decimal('estimated_fee', 12, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['payment_type', 'location_type'], 'idx_jenis_payment_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_kegiatan');
    }
};
