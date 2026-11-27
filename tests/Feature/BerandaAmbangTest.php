<?php

namespace Tests\Feature;

use App\Models\AmbangStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PSKE-106 · Legenda dan filter status di beranda mengikuti ambang yang diatur Superadmin.
 */
class BerandaAmbangTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_memakai_ambang_awal(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kurang dari 14 hari')
            ->assertSee('14 sampai 30 hari')
            ->assertSee('Lebih dari 30 hari')
            ->assertSee('{"kritis":14,"aman":30}', false);
    }

    public function test_beranda_mengikuti_ambang_yang_diubah_superadmin(): void
    {
        AmbangStatus::aktif()->update(['kritis_hari' => 10, 'aman_hari' => 45]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Kurang dari 10 hari')
            ->assertSee('10 sampai 45 hari')
            ->assertSee('Lebih dari 45 hari')
            ->assertSee('{"kritis":10,"aman":45}', false)
            ->assertDontSee('Kurang dari 14 hari');
    }
}
