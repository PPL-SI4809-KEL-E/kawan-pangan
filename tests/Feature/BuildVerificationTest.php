<?php

namespace Tests\Feature;

use Tests\TestCase;

class BuildVerificationTest extends TestCase
{
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

        $this->withoutVite();
        $landingResponse = $this->get('/');
        $landingResponse->assertStatus(200);
    }
}

