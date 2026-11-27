<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test health check API endpoint hanya mengembalikan status dan timestamp.
     */
    public function test_api_health_endpoint_returns_success(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertExactJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'healthy'])
            ->assertJsonMissingPath('author')
            ->assertJsonMissingPath('sprint');
    }

    /**
     * Route /api/user dihapus karena guard sanctum tidak digunakan.
     */
    public function test_api_user_endpoint_tidak_tersedia(): void
    {
        $this->getJson('/api/user')->assertStatus(404);
    }
}
