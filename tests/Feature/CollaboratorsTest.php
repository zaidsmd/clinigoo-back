<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaboratorsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'type' => UserType::Admin->value,
            'email' => 'superadmin@clinigoo.ma',
        ]);
    }

    public function test_admin_can_list_collaborators(): void
    {
        User::factory()->count(3)->create([
            'type' => UserType::Admin->value,
        ]);
        User::factory()->count(2)->create([
            'type' => UserType::Patient->value,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/api/admin/collaborators');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(4, $data); // superadmin + 3 admins
    }

    public function test_admin_can_create_collaborator(): void
    {
        $payload = [
            'name' => 'Dr. Karim Tazi',
            'email' => 'karim.tazi@clinigoo.ma',
            'password' => 'SecurePass123!',
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/admin/collaborators', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'name' => 'Dr. Karim Tazi',
            'email' => 'karim.tazi@clinigoo.ma',
            'type' => UserType::Admin->value,
        ]);
    }

    public function test_admin_can_update_collaborator(): void
    {
        $collaborator = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@clinigoo.ma',
            'type' => UserType::Admin->value,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/api/admin/collaborators/{$collaborator->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@clinigoo.ma',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $collaborator->id,
            'name' => 'Updated Name',
            'email' => 'updated@clinigoo.ma',
        ]);
    }

    public function test_admin_can_delete_other_collaborator(): void
    {
        $collaborator = User::factory()->create([
            'type' => UserType::Admin->value,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->deleteJson("/api/admin/collaborators/{$collaborator->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', [
            'id' => $collaborator->id,
        ]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->deleteJson("/api/admin/collaborators/{$this->admin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
        ]);
    }
}

