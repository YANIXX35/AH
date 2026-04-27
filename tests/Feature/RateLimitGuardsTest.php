<?php

namespace Tests\Feature;

use Tests\TestCase;

class RateLimitGuardsTest extends TestCase
{
    public function test_login_route_is_rate_limited(): void
    {
        $lastResponse = null;
        for ($i = 0; $i < 9; $i++) {
            $lastResponse = $this->post('/login', [
                'email' => 'nobody@example.com',
                'password' => 'invalid-password',
            ]);
        }

        $this->assertNotNull($lastResponse);
        $lastResponse->assertStatus(429);
    }
}
