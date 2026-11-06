<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PSKE-81 · Beranda publik memakai design system dan berisi konten untuk pengguna.
 */
class BerandaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_beranda_tampil_dengan_aset_design_system(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kawan Pangan')
            ->assertSee('app-header', false)
            ->assertSee(route('registrasi.create'), false);
    }

    public function test_beranda_menjelaskan_ambang_status_wilayah(): void
    {
        $this->get('/')
            ->assertSeeInOrder(['Aman', 'Lebih dari 30 hari', 'Waspada', '14 sampai 30 hari', 'Kritis', 'Kurang dari 14 hari']);
    }

    public function test_beranda_tanpa_info_teknis_nim_dan_data_contoh(): void
    {
        $this->get('/')
            ->assertDontSee('PBI-01')
            ->assertDontSee('Sprint 1')
            ->assertDontSee('/api/health')
            ->assertDontSee('102022400')
            ->assertDontSee('Gudang Pangan Utama')
            ->assertDontSee('Pangan Kota');
    }
}
