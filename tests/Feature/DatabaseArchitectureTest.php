<?php

namespace Tests\Feature;

use App\Models\Protokol;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_and_seeders_run_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@kemdikbud.go.id',
        ]);

        $this->assertDatabaseCount('jenis_kegiatan', 4);
        $this->assertDatabaseCount('hari_libur', 3);
        $this->assertDatabaseCount('protokols', 10);

        $activeCount = Protokol::active()->count();
        $this->assertEquals(9, $activeCount);
    }
}
