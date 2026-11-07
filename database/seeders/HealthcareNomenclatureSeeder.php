<?php

namespace Database\Seeders;

use App\Enums\HealthcareProfession;
use App\Enums\HealthcareServiceCategory;
use App\Models\Profession;
use App\Models\ProfessionDocument;
use App\Models\Service;
use Illuminate\Database\Seeder;

class HealthcareNomenclatureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Common required documents
        $docCin = ProfessionDocument::firstOrCreate(
            ['name' => "Carte Nationale d'Identité (CIN)"],
            ['description' => "Copie recto-verso de la CIN marocaine", 'formats' => ['pdf', 'jpg', 'png'], 'is_active' => true]
        );
        $docDiploma = ProfessionDocument::firstOrCreate(
            ['name' => "Diplôme d'État ou Titre Reconnu"],
            ['description' => "Diplôme national attestant des qualifications requises", 'formats' => ['pdf', 'jpg', 'png'], 'is_active' => true]
        );
        $docAuth = ProfessionDocument::firstOrCreate(
            ['name' => "Autorisation d'Exercer"],
            ['description' => "Délivrée par le Secrétariat Général du Gouvernement ou le Ministère de la Santé", 'formats' => ['pdf'], 'is_active' => true]
        );
        $docOrder = ProfessionDocument::firstOrCreate(
            ['name' => "Attestation d'Inscription à l'Ordre"],
            ['description' => "Attestation de l'Ordre National professionnel concerné", 'formats' => ['pdf'], 'is_active' => true]
        );
        $docCv = ProfessionDocument::firstOrCreate(
            ['name' => "Curriculum Vitae (CV)"],
            ['description' => "Détail du parcours et des expériences professionnelles", 'formats' => ['pdf'], 'is_active' => true]
        );

        $defaultDocList = [
            $docCin->id => ['required' => true],
            $docDiploma->id => ['required' => true],
            $docAuth->id => ['required' => true],
            $docOrder->id => ['required' => false],
            $docCv->id => ['required' => false],
        ];

        // 2. The 6 Fixed Professions & their Services
        foreach (HealthcareProfession::cases() as $profEnum) {
            /** @var Profession $profession */
            $profession = Profession::firstOrCreate(
                ['slug' => $profEnum->value],
                [
                    'name' => $profEnum->label(),
                    'description' => "Praticien habilité aux actes de {$profEnum->label()} à domicile.",
                    'icon' => $profEnum->icon(),
                    'is_active' => true,
                ]
            );

            // Ensure name is up to date
            $profession->update([
                'name' => $profEnum->label(),
                'icon' => $profEnum->icon(),
                'is_active' => true,
            ]);

            // Sync standard document requirements
            $profession->documents()->syncWithoutDetaching($defaultDocList);

            // Sync services defined in Professions.md
            $serviceIds = [];
            foreach ($profEnum->catalogServices() as $index => $srvData) {
                /** @var Service $service */
                $service = Service::firstOrCreate(
                    ['name' => $srvData['name']],
                    [
                        'category' => $profEnum->category()->value,
                        'description' => $srvData['description'],
                        'base_price' => match ($profEnum->category()) {
                            HealthcareServiceCategory::Medical => 300,
                            HealthcareServiceCategory::Paramedical => 200,
                            HealthcareServiceCategory::Soins => 120,
                            HealthcareServiceCategory::Assistance => 80,
                            HealthcareServiceCategory::MaternitePediatrie => 250,
                        },
                        'duration_minutes' => match ($profEnum->category()) {
                            HealthcareServiceCategory::Medical => 45,
                            HealthcareServiceCategory::Paramedical => 45,
                            HealthcareServiceCategory::Soins => 30,
                            HealthcareServiceCategory::Assistance => 60,
                            HealthcareServiceCategory::MaternitePediatrie => 60,
                        },
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ]
                );

                // Make sure category is synchronized
                if ($service->category !== $profEnum->category()) {
                    $service->update(['category' => $profEnum->category()->value]);
                }

                $serviceIds[] = $service->id;
            }

            $profession->services()->sync($serviceIds);
        }
    }
}
