<?php

use App\Enums\ProfessionalStatus;
use App\Enums\UserType;
use App\Models\Beneficiary;
use App\Models\PatientProfile;
use App\Models\Profession;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultPassword = Hash::make('password1');

        // 1. Administrators
        $admins = [
            [
                'name' => 'Zakariae',
                'email' => 'zakariae@clinigoo.ma',
            ],
            [
                'name' => 'Bilal',
                'email' => 'bilal@clinigoo.ma',
            ],
            [
                'name' => 'Yassir',
                'email' => 'yassir@clinigoo.ma',
            ],
        ];

        foreach ($admins as $adminData) {
            User::updateOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'type' => UserType::Admin->value,
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                ]
            );
        }

        // 2. Healthcare Professionals
        // 4. Medecin generaliste
        $medecinUser = User::updateOrCreate(
            ['email' => 'medecin@clinigoo.ma'],
            [
                'name' => 'Dr. Médecin Généraliste',
                'type' => UserType::Professional->value,
                'profession' => 'medecin_generaliste',
                'password' => $defaultPassword,
                'email_verified_at' => now(),
            ]
        );

        $medecinProfession = Profession::where('slug', 'medecin_generaliste')->first();

        $medecinProfile = ProfessionalProfile::updateOrCreate(
            ['user_id' => $medecinUser->id],
            [
                'profession_id' => $medecinProfession?->id,
                'first_name' => 'Médecin',
                'last_name' => 'Généraliste',
                'sexe' => 'M',
                'id_number' => 'MED-10001',
                'phone' => '+212611223344',
                'city' => 'Casablanca',
                'bio' => 'Médecin généraliste diplômé, habilité pour les consultations et soins à domicile.',
                'status' => ProfessionalStatus::Validated,
                'is_available' => true,
                'submitted_at' => now()->subDays(5),
                'validated_at' => now()->subDays(2),
            ]
        );

        if ($medecinProfession) {
            $serviceIds = $medecinProfession->services()->pluck('services.id')->all();
            if (! empty($serviceIds)) {
                $medecinProfile->services()->sync($serviceIds);
            }
        }

        // 5. Infirmiere
        $infirmierUser = User::updateOrCreate(
            ['email' => 'infermie@clinigoo.ma'],
            [
                'name' => 'Infirmière / Infirmier Soins',
                'type' => UserType::Professional->value,
                'profession' => 'infirmiere',
                'password' => $defaultPassword,
                'email_verified_at' => now(),
            ]
        );

        $infirmiereProfession = Profession::where('slug', 'infirmiere')->first();

        $infirmierProfile = ProfessionalProfile::updateOrCreate(
            ['user_id' => $infirmierUser->id],
            [
                'profession_id' => $infirmiereProfession?->id,
                'first_name' => 'Infirmière',
                'last_name' => 'Soignante',
                'sexe' => 'F',
                'id_number' => 'INF-10002',
                'phone' => '+212622334455',
                'city' => 'Casablanca',
                'bio' => 'Infirmière d\'État qualifiée pour injections, prélèvements, pansements et perfusions.',
                'status' => ProfessionalStatus::Validated,
                'is_available' => true,
                'submitted_at' => now()->subDays(4),
                'validated_at' => now()->subDay(),
            ]
        );

        if ($infirmiereProfession) {
            $serviceIds = $infirmiereProfession->services()->pluck('services.id')->all();
            if (! empty($serviceIds)) {
                $infirmierProfile->services()->sync($serviceIds);
            }
        }

        // 3. Patients (patient1 -> patient6)
        $patientCities = ['Casablanca', 'Rabat', 'Marrakech', 'Tanger', 'Agadir', 'Fès'];

        for ($i = 1; $i <= 6; $i++) {
            $email = "patient{$i}@clinigoo.ma";
            $firstName = "Patient";
            $lastName = "Numéro {$i}";
            $fullName = "{$firstName} {$lastName}";
            $phone = sprintf('+21260000000%d', $i);
            $gender = ($i % 2 === 0) ? 'female' : 'male';
            $sexe = ($i % 2 === 0) ? 'F' : 'M';
            $city = $patientCities[($i - 1) % count($patientCities)];

            $patientUser = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $fullName,
                    'type' => UserType::Patient->value,
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                ]
            );

            PatientProfile::updateOrCreate(
                ['user_id' => $patientUser->id],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'birthday' => now()->subYears(20 + $i * 3)->format('Y-m-d'),
                    'sexe' => $sexe,
                    'phone' => $phone,
                    'email' => $email,
                ]
            );

            Beneficiary::updateOrCreate(
                [
                    'user_id' => $patientUser->id,
                    'is_default' => true,
                ],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'relationship' => 'self',
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears(20 + $i * 3)->format('Y-m-d'),
                    'phone' => $phone,
                    'cin_amo_cnss' => sprintf('BK%05d', 10000 + $i),
                    'medical_notes' => "Dossier patient principal ({$city}).",
                    'is_default' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $emails = [
            'zakariae@clinigoo.ma',
            'bilal@clinigoo.ma',
            'yassir@clinigoo.ma',
            'medecin@clinigoo.ma',
            'infermier@clinigoo.ma',
            'patient1@clinigoo.ma',
            'patient2@clinigoo.ma',
            'patient3@clinigoo.ma',
            'patient4@clinigoo.ma',
            'patient5@clinigoo.ma',
            'patient6@clinigoo.ma',
        ];

        User::whereIn('email', $emails)->delete();
    }
};

