<?php

use App\Models\Address;
use App\Models\Beneficiary;
use App\Models\CareRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('unauthenticated users cannot access admin care-requests endpoints', function () {
    $this->getJson('/api/admin/care-requests')->assertStatus(401);
    $this->getJson('/api/admin/care-requests/status-counts')->assertStatus(401);
    $this->getJson('/api/admin/care-requests/1')->assertStatus(401);
    $this->putJson('/api/admin/care-requests/1/status', ['status' => 'completed'])->assertStatus(401);
    $this->deleteJson('/api/admin/care-requests/1')->assertStatus(401);

    // Demandes aliases
    $this->getJson('/api/admin/demandes')->assertStatus(401);
    $this->getJson('/api/admin/demandes/status-counts')->assertStatus(401);
});

test('non-admin users cannot access admin care-requests endpoints', function () {
    $patient = User::factory()->patient()->create();
    $token = Auth::guard('api')->login($patient);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/care-requests')
        ->assertStatus(401);
});

test('admin can list care requests with datatable structure and status counts', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $user = User::factory()->patient()->create();
    $beneficiary = Beneficiary::factory()->create(['user_id' => $user->id]);
    $service = Service::factory()->create();
    $address = Address::factory()->create(['user_id' => $user->id]);

    CareRequest::factory()->create([
        'user_id' => $user->id,
        'beneficiary_id' => $beneficiary->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => 'pending',
    ]);

    CareRequest::factory()->create([
        'user_id' => $user->id,
        'beneficiary_id' => $beneficiary->id,
        'service_id' => $service->id,
        'address_id' => $address->id,
        'status' => 'completed',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/care-requests');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'status_counts' => [
                'all',
                'pending',
                'matched',
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
            ],
        ]);

    expect($response->json('recordsTotal'))->toBe(2);
    expect($response->json('status_counts.pending'))->toBe(1);
    expect($response->json('status_counts.completed'))->toBe(1);
});

test('admin can filter care requests by status', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $user = User::factory()->patient()->create();
    $beneficiary = Beneficiary::factory()->create(['user_id' => $user->id]);
    $service = Service::factory()->create();

    CareRequest::factory()->count(3)->create([
        'user_id' => $user->id,
        'beneficiary_id' => $beneficiary->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    CareRequest::factory()->count(2)->create([
        'user_id' => $user->id,
        'beneficiary_id' => $beneficiary->id,
        'service_id' => $service->id,
        'status' => 'completed',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/care-requests?status=pending');

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(3);
});

test('admin can fetch standalone status counts', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $user = User::factory()->patient()->create();
    $beneficiary = Beneficiary::factory()->create(['user_id' => $user->id]);
    $service = Service::factory()->create();

    CareRequest::factory()->count(4)->create([
        'user_id' => $user->id,
        'beneficiary_id' => $beneficiary->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/admin/care-requests/status-counts');

    $response->assertOk()
        ->assertJson([
            'all' => 4,
            'pending' => 4,
        ]);
});

test('admin can show, update status and delete a care request', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $user = User::factory()->patient()->create();
    $beneficiary = Beneficiary::factory()->create(['user_id' => $user->id]);
    $service = Service::factory()->create();

    $careRequest = CareRequest::factory()->create([
        'user_id' => $user->id,
        'beneficiary_id' => $beneficiary->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    // Show
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/care-requests/{$careRequest->id}")
        ->assertOk()
        ->assertJsonPath('id', $careRequest->id);

    // Update status
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/admin/care-requests/{$careRequest->id}/status", [
            'status' => 'matched',
            'notes' => 'Praticien affecté par l\'administrateur.',
        ])
        ->assertOk()
        ->assertJsonPath('care_request.status', 'matched');

    $this->assertDatabaseHas('care_requests', [
        'id' => $careRequest->id,
        'status' => 'matched',
    ]);

    // Delete
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/care-requests/{$careRequest->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('care_requests', [
        'id' => $careRequest->id,
    ]);
});

