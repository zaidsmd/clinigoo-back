<?php

use App\Models\Profession;
use App\Models\ProfessionDocument;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('unauthenticated users cannot access admin profession routes', function () {
    $response = $this->getJson('/api/admin/professions');
    $response->assertStatus(401);
});

test('admin cannot create, update or delete a profession because professions are fixed', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);
    $prof = Profession::factory()->create();

    // POST /api/admin/professions
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/professions', ['name' => 'Nouvelle Profession']);
    expect(in_array($response->status(), [403, 405]))->toBeTrue();

    // PUT /api/admin/professions/{id} without documents (trying to modify profession itself)
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/admin/professions/{$prof->id}", ['name' => 'Autre Nom']);
    expect(in_array($response->status(), [403, 405]))->toBeTrue();

    // DELETE /api/admin/professions/{id}
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/professions/{$prof->id}");
    expect(in_array($response->status(), [403, 405]))->toBeTrue();
});

test('admin can update profession document requirements', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $profession = Profession::factory()->create();
    $doc1 = ProfessionDocument::factory()->create();
    $doc2 = ProfessionDocument::factory()->create();

    $payload = [
        'documents' => [
            ['id' => $doc1->id, 'required' => true],
            ['id' => $doc2->id, 'required' => false],
        ],
    ];

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/admin/professions/{$profession->id}/documents", $payload);

    $response->assertOk();

    $this->assertDatabaseHas('profession_profession_document', [
        'profession_id' => $profession->id,
        'profession_document_id' => $doc1->id,
        'required' => true,
    ]);

    $this->assertDatabaseHas('profession_profession_document', [
        'profession_id' => $profession->id,
        'profession_document_id' => $doc2->id,
        'required' => false,
    ]);
});

test('clients can list active professions with services and documents', function () {
    $user = User::factory()->patient()->create();
    $token = Auth::guard('api')->login($user);

    $service = Service::factory()->create();

    $doc = ProfessionDocument::factory()->create([
        'name' => 'Carte CPS',
        'formats' => ['pdf', 'png'],
    ]);

    $profession = Profession::factory()->create(['name' => 'Infirmier Libéral', 'is_active' => true]);
    $profession->services()->attach($service->id);
    $profession->documents()->attach($doc->id, ['required' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/professions');

    $response->assertOk()
        ->assertJsonStructure([
            'items' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'slug',
                    'icon',
                    'is_active',
                    'services' => [
                        '*' => ['id', 'name', 'base_price'],
                    ],
                    'documents' => [
                        '*' => ['id', 'name', 'formats', 'enabled_formats', 'required'],
                    ],
                ],
            ],
            'data',
        ]);

    $first = $response->json('items.0');
    expect($first['documents'][0]['required'])->toBeTrue()
        ->and($first['documents'][0]['formats'])->toEqual(['pdf', 'png'])
        ->and($first['documents'][0]['enabled_formats'])->toEqual(['pdf', 'png']);
});

test('clients can view single profession with required and optional documents', function () {
    $user = User::factory()->patient()->create();
    $token = Auth::guard('api')->login($user);

    $service = Service::factory()->create();

    $reqDoc = ProfessionDocument::factory()->create([
        'name' => 'Diplôme',
        'formats' => ['pdf'],
    ]);

    $optDoc = ProfessionDocument::factory()->create([
        'name' => 'Recommandation',
        'formats' => ['pdf', 'jpg'],
    ]);

    $profession = Profession::factory()->create(['name' => 'Aide-Soignant']);
    $profession->services()->attach($service->id);
    $profession->documents()->attach([
        $reqDoc->id => ['required' => true],
        $optDoc->id => ['required' => false],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/professions/{$profession->id}");

    $response->assertOk()
        ->assertJsonPath('name', 'Aide-Soignant');

    $docs = collect($response->json('documents'));
    $diplome = $docs->firstWhere('id', $reqDoc->id);
    $recom = $docs->firstWhere('id', $optDoc->id);

    expect($diplome['required'])->toBeTrue()
        ->and($recom['required'])->toBeFalse();
});

test('clients can list profession documents and view enabled formats', function () {
    $user = User::factory()->patient()->create();
    $token = Auth::guard('api')->login($user);

    ProfessionDocument::factory()->create([
        'name' => 'Assurance RC Pro',
        'formats' => ['pdf'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/profession-documents');

    $response->assertOk()
        ->assertJsonStructure([
            'items' => [
                '*' => ['id', 'name', 'formats', 'enabled_formats', 'is_active'],
            ],
            'data',
        ]);
});

test('admin can create and update profession documents', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    // Create document
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/profession-documents', [
            'name' => 'Carte Ordinale',
            'description' => 'Inscription au tableau de l ordre',
            'formats' => ['pdf', 'png', 'jpeg'],
            'is_active' => true,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'name' => 'Carte Ordinale',
            'formats' => ['pdf', 'png', 'jpeg'],
            'is_active' => true,
        ]);

    $docId = $response->json('id');

    // Update document
    $updateResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/admin/profession-documents/{$docId}", [
            'name' => 'Carte de l Ordre National',
            'formats' => ['pdf'],
        ]);

    $updateResponse->assertOk()
        ->assertJson([
            'name' => 'Carte de l Ordre National',
            'formats' => ['pdf'],
        ]);

    $this->assertDatabaseHas('profession_documents', [
        'id' => $docId,
        'name' => 'Carte de l Ordre National',
    ]);
});

test('professions cannot be created, updated, or deleted via admin CRUD because they are fixed', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $profession = Profession::factory()->create(['name' => 'Test']);

    $res1 = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/professions', ['name' => 'Sage-Femme']);
    expect(in_array($res1->status(), [403, 405]))->toBeTrue();

    $res2 = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/admin/professions/{$profession->id}", ['name' => 'Updated']);
    expect(in_array($res2->status(), [403, 405]))->toBeTrue();

    $res3 = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/professions/{$profession->id}");
    expect(in_array($res3->status(), [403, 405]))->toBeTrue();
});

test('admin can delete a profession document', function () {
    $admin = User::factory()->admin()->create();
    $token = Auth::guard('admin')->login($admin);

    $doc = ProfessionDocument::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/profession-documents/{$doc->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('profession_documents', ['id' => $doc->id]);
});

test('profession document checks if format is allowed', function () {
    $doc = ProfessionDocument::factory()->create([
        'formats' => ['pdf', 'png'],
    ]);

    expect($doc->isFormatAllowed('pdf'))->toBeTrue()
        ->and($doc->isFormatAllowed('PDF'))->toBeTrue()
        ->and($doc->isFormatAllowed('png'))->toBeTrue()
        ->and($doc->isFormatAllowed('docx'))->toBeFalse()
        ->and($doc->isFormatAllowed('exe'))->toBeFalse();
});

test('service model has inverse professions relationship', function () {
    $service = Service::factory()->create();
    $profession = Profession::factory()->create();

    $service->professions()->attach($profession->id);

    expect($service->professions->count())->toBe(1)
        ->and($service->professions->first()->id)->toBe($profession->id);
});
