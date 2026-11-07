<?php

namespace Tests\Feature;

use App\Enums\ProfessionalStatus;
use App\Models\City;
use App\Models\Profession;
use App\Models\ProfessionDocument;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfessionalRegistrationFlowTest extends TestCase
{
    public function test_can_fetch_public_cities(): void
    {
        $response = $this->getJson('/api/public/cities');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    public function test_can_check_uniqueness_for_available_data(): void
    {
        $response = $this->postJson('/api/public/professionals/check-unique', [
            'email' => 'unique.test.' . time() . '@clinigoo.ma',
            'cin' => 'TEST' . rand(1000, 9999),
        ]);

        $response->assertStatus(200)
            ->assertJson(['available' => true]);
    }

    public function test_check_uniqueness_fails_if_email_or_cin_exists(): void
    {
        $user = User::factory()->create([
            'email' => 'existing.pro.' . time() . rand(100, 999) . '@clinigoo.ma',
        ]);

        $cin = 'CIN' . rand(1000, 9999);
        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'id_number' => $cin,
        ]);

        $response = $this->postJson('/api/public/professionals/check-unique', [
            'email' => $user->email,
            'cin' => $cin,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'cin']);
    }

    public function test_registration_fails_if_email_or_cin_already_registered(): void
    {
        $existingEmail = 'existing.' . time() . rand(100, 999) . '@clinigoo.ma';
        $existingCin = 'BE' . rand(100000, 999999);

        $user = User::factory()->create([
            'email' => $existingEmail,
        ]);

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'id_number' => $existingCin,
        ]);

        $payload = [
            'profession_id' => '1',
            'profession' => 'medecin_generaliste',
            'personal' => [
                'sex' => 'M',
                'first_name' => 'Amine',
                'last_name' => 'Bennani',
                'cin' => $existingCin,
                'birthday' => '1990-01-01',
                'phone' => '+212 661 00 00 00',
                'city' => 'Casablanca',
                'email' => $existingEmail,
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ],
            'email' => $existingEmail,
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ];

        $response = $this->postJson('/api/public/professionals', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['personal.email', 'personal.cin']);
    }

    public function test_can_register_and_complete_profile_post_login(): void
    {
        Storage::fake('public');

        $email = 'flow.doctor.' . time() . rand(100, 999) . '@clinigoo.ma';
        $cin = 'BK' . rand(100000, 999999);

        // 1. Initial 3-step registration
        $payload = [
            'profession_id' => '1',
            'profession' => 'medecin_generaliste',
            'personal' => [
                'sex' => 'M',
                'first_name' => 'Driss',
                'last_name' => 'El Alami',
                'cin' => $cin,
                'birthday' => '1985-04-20',
                'phone' => '+212 662 33 44 55',
                'city' => 'Rabat',
                'email' => $email,
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ],
            'email' => $email,
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ];

        $regResponse = $this->postJson('/api/public/professionals', $payload);

        $regResponse->assertStatus(201);
        $token = $regResponse->json('access_token');
        $this->assertNotEmpty($token);

        // Status is initially draft
        $this->assertEquals(ProfessionalStatus::Draft->value, $regResponse->json('status'));

        // 2. Login via /api/auth/login with the new professional account
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'secret1234',
        ]);
        $loginResponse->assertStatus(200);
        $authToken = $loginResponse->json('access_token');
        $this->assertNotEmpty($authToken);

        // 3. Call /api/auth/me (verifies no getBirthdayAttribute exception occurs upon serialization)
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $authToken)
            ->getJson('/api/auth/me');
        $meResponse->assertStatus(200);
        $this->assertEquals($email, $meResponse->json('email'));
        $this->assertEquals('professional', $meResponse->json('type'));
        $this->assertNotNull($meResponse->json('professional_profile'));
        $this->assertEquals('1985-04-20', substr((string)$meResponse->json('professional_profile.birthday'), 0, 10));

        // 4. Post-login: view dossier
        $dossierResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/professional/dossier');

        $dossierResponse->assertStatus(200);
        $this->assertEquals('Driss El Alami', $dossierResponse->json('full_name'));
        $this->assertEquals('Rabat', $dossierResponse->json('city'));
        $this->assertEquals('1985-04-20', $dossierResponse->json('birthday'));
    }
}
