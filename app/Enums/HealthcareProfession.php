<?php

namespace App\Enums;

enum HealthcareProfession: string
{
    case MedecinGeneraliste = 'medecin_generaliste';
    case Pediatre = 'pediatre';
    case SageFemme = 'sage_femme';
    case Kinesitherapeute = 'kinesitherapeute';
    case Infirmiere = 'infirmiere';
    case AideSoignante = 'aide_soignante';

    /**
     * Official French designation of the healthcare profession.
     */
    public function label(): string
    {
        return match ($this) {
            self::MedecinGeneraliste => 'Médecin généraliste',
            self::Pediatre => 'Pédiatre',
            self::SageFemme => 'Sage-femme',
            self::Kinesitherapeute => 'Kinésithérapeute',
            self::Infirmiere => 'Infirmière / Infirmier',
            self::AideSoignante => 'Aide-soignante / Garde-malade',
        };
    }

    /**
     * Assigned service category for this profession.
     */
    public function category(): HealthcareServiceCategory
    {
        return match ($this) {
            self::MedecinGeneraliste => HealthcareServiceCategory::Medical,
            self::Pediatre => HealthcareServiceCategory::Medical,
            self::SageFemme => HealthcareServiceCategory::Paramedical,
            self::Kinesitherapeute => HealthcareServiceCategory::Paramedical,
            self::Infirmiere => HealthcareServiceCategory::Soins,
            self::AideSoignante => HealthcareServiceCategory::Assistance,
        };
    }

    /**
     * Standard icon for UI representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MedecinGeneraliste => 'solar:stethoscope-bold-duotone',
            self::Pediatre => 'solar:baby-bold-duotone',
            self::SageFemme => 'solar:heart-bold-duotone',
            self::Kinesitherapeute => 'solar:walking-bold-duotone',
            self::Infirmiere => 'solar:syringe-bold-duotone',
            self::AideSoignante => 'solar:hand-heart-bold-duotone',
        };
    }

    /**
     * Associated catalog services defined in Professions.md.
     *
     * @return list<array{name: string, description: string}>
     */
    public function catalogServices(): array
    {
        return match ($this) {
            self::MedecinGeneraliste => [
                ['name' => 'Consultation générale', 'description' => 'Diagnostic, ordonnances, certificats.'],
                ['name' => 'Urgence non vitale', 'description' => 'Fièvres, douleurs, gastro-entérites, crises.'],
                ['name' => 'Pathologies chroniques', 'description' => 'Suivi du diabète, HTA, traitements.'],
                ['name' => 'Bilan senior & routine', 'description' => 'Évaluation de la fragilité & check-up.'],
                ['name' => 'Orientation médicale', 'description' => 'Prescription de labo et imagerie.'],
            ],
            self::Pediatre => [
                ['name' => 'Suivi de croissance', 'description' => 'Poids, taille, développement moteur.'],
                ['name' => 'Suivi vaccinal', 'description' => 'Vaccins du calendrier national à domicile.'],
                ['name' => 'Pathologies infantiles', 'description' => 'Bronchiolites, otites, fièvres, poussées.'],
                ['name' => 'Conseils puériculture', 'description' => 'Alimentation, diversification, sommeil.'],
            ],
            self::SageFemme => [
                ['name' => 'Suivi post-partum', 'description' => 'Santé maman, cicatrisation (périnée/césarienne).'],
                ['name' => 'Soins du nouveau-né', 'description' => 'Pesée, soins du cordon, premier bain.'],
                ['name' => 'Allaitement', 'description' => 'Conseils de position, prévention des engorgements.'],
                ['name' => 'Rééducation périnéale', 'description' => 'Séances post-natales à domicile.'],
                ['name' => 'Suivi de grossesse', 'description' => 'Monitoring sur ordonnance & pré-partum.'],
            ],
            self::Kinesitherapeute => [
                ['name' => 'Rééducation fonctionnelle', 'description' => 'Post-fracture, entorse, prothèse/chirurgie.'],
                ['name' => 'Kiné respiratoire', 'description' => 'Désencombrement bronchique (bébé & senior).'],
                ['name' => 'Rééducation neurologique', 'description' => 'Post-AVC, Parkinson, sclérose.'],
                ['name' => 'Maintien de l\'autonomie', 'description' => 'Remise à la marche, prévention chutes.'],
                ['name' => 'Drainage & massages', 'description' => 'Traitement des œdèmes & douleurs.'],
            ],
            self::Infirmiere => [
                ['name' => 'Prélèvements sanguins', 'description' => 'Prises de sang & envoi au labo.'],
                ['name' => 'Injections & perfusions', 'description' => 'IM, SC, antalgiques, suivi IV.'],
                ['name' => 'Soins de plaies', 'description' => 'Pansements simples, chirurgicaux, escarres.'],
                ['name' => 'Suivi des constantes', 'description' => 'Contrôle tension, glycémie, insuline.'],
                ['name' => 'Sondes & stomies', 'description' => 'Entretien de cathéters, stomies, sondes.'],
            ],
            self::AideSoignante => [
                ['name' => 'Hygiène & toilette', 'description' => 'Toilette au lit/lavabo, hygiène dentaire.'],
                ['name' => 'Aide à la mobilité', 'description' => 'Transferts lit/fauteuil, déplacements.'],
                ['name' => 'Habillage & confort', 'description' => 'Aide quotidienne à l\'habillage.'],
                ['name' => 'Repas & médicaments', 'description' => 'Aide à la prise de repas & rappels.'],
                ['name' => 'Garde & présence', 'description' => 'Surveillance continue jour/nuit.'],
            ],
        };
    }

    /**
     * Returns all values as a list of strings.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolves an enum instance from a string, slug, or numeric index.
     */
    public static function fromInput(mixed $input): ?self
    {
        if ($input instanceof self) {
            return $input;
        }

        if (is_numeric($input)) {
            $cases = self::cases();
            $index = ((int) $input) - 1;

            return $cases[$index] ?? null;
        }

        if (is_string($input)) {
            $normalized = strtolower(trim($input));
            foreach (self::cases() as $case) {
                if ($case->value === $normalized) {
                    return $case;
                }
                if (strtolower($case->label()) === $normalized) {
                    return $case;
                }
                if (strtolower($case->name) === $normalized) {
                    return $case;
                }
            }
        }

        return null;
    }

    /**
     * Structured array representing the profession for API output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'key' => $this->value,
            'value' => $this->value,
            'name' => $this->label(),
            'label' => $this->label(),
            'icon' => $this->icon(),
            'category' => [
                'id' => $this->category()->value,
                'name' => $this->category()->label(),
            ],
            'catalog_services' => $this->catalogServices(),
        ];
    }

    /**
     * Return all 6 fixed professions formatted for APIs and dropdowns.
     *
     * @return list<array<string, mixed>>
     */
    public static function allFormatted(): array
    {
        return array_map(fn (self $p) => $p->toArray(), self::cases());
    }
}

