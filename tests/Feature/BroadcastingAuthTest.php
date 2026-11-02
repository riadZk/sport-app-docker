<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class BroadcastingAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force reverb driver for these tests — the log driver returns null
        // for auth requests. In production, BROADCAST_CONNECTION=log means
        // broadcasting is disabled and this endpoint is not used.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-reverb-key',
            'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
            'broadcasting.connections.reverb.app_id' => 'test-reverb-app-id',
        ]);

        // Broadcast::purge() forces the BroadcastManager to forget cached
        // drivers so the next call re-resolves with our test config.
        Broadcast::purge();

        // Re-register channel authorization callbacks on the fresh driver.
        // The boot-time registration ran against the log driver, not reverb.
        require base_path('routes/channels.php');
    }

    /**
     * Unauthenticated requests to the broadcasting auth endpoint are rejected.
     */
    public function test_broadcasting_auth_rejects_unauthenticated_request(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-user.1',
        ]);

        $response->assertStatus(401);
    }

    /**
     * An authenticated user can authorize their own private channel.
     */
    public function test_authenticated_user_can_authorize_own_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-user.{$user->id}",
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }

    /**
     * A user cannot authorize another user's private channel.
     */
    public function test_user_cannot_authorize_another_users_channel(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-user.{$otherUser->id}",
            ]);

        $response->assertStatus(403);
    }

    /**
     * A valid Bearer token in the Authorization header also authenticates.
     * This confirms the endpoint works for API-only (non-session) clients.
     */
    public function test_bearer_token_authenticates_broadcasting_auth(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => "private-user.{$user->id}",
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }
}
