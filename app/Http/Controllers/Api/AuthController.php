<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Alumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nombre' => 'Test',
            'apellido' => 'User',
            'pais' => 'España',
            'telefono' => '123456789',
            'direccion' => 'Test Address'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'token'
                ]);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'email' => 'test@example.com'
        ]);

        $this->assertDatabaseHas('alumno', [
            'nombre' => 'Test',
            'apellido' => 'User'
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        Alumno::factory()->create([
            'user_id' => $user->id
        ]);

        $loginData = [
            'identifier' => 'testuser',
            'password' => 'password123',
            'device_id' => 'test-device-123'
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'token',
                    'type'
                ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'test-device-123'
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $deviceId = 'test-device-123';
        $this->createUserDevice($user, $token, $deviceId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Device-ID' => $deviceId
        ])->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Sesión cerrada exitosamente'
                ]);

        $this->assertDatabaseMissing('user_devices', [
            'user_id' => $user->id,
            'device_id' => $deviceId
        ]);
    }

    private function createUserDevice($user, $token, $deviceId): void
    {
        $user->devices()->create([
            'device_id' => $deviceId,
            'token' => $token,
            'last_activity' => now()
        ]);
    }
}
