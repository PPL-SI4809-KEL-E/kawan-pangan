<?php

namespace Tests\Feature;

use Tests\TestCase;

class BuildVerificationTest extends TestCase
{
    /**
     * Memverifikasi bahwa aset build Vite (manifest) berhasil dibuat atau tersedia.
     */
    public function test_frontend_assets_manifest_exists(): void
    {
        $manifestPath = public_path('build/manifest.json');

        $this->assertFileExists(
            $manifestPath,
            'File public/build/manifest.json belum ada. Pastikan npm run build sudah dijalankan.'
        );
    }

    /**
     * Memverifikasi bahwa endpoint health check dan halaman utama mengembalikan status sukses.
     */
    public function test_application_landing_and_health_check_return_ok(): void
    {
        $healthResponse = $this->getJson('/api/health');
        $healthResponse->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ]);

        $landingResponse = $this->get('/');
        $landingResponse->assertStatus(200);
    }
}
