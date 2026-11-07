<?php

namespace App\Enums;

enum HealthcareServiceCategory: string
{
    case Medical = 'medical';
    case Paramedical = 'paramedical';
    case Soins = 'soins';
    case Assistance = 'assistance';
    case MaternitePediatrie = 'maternite_pediatrie';

    /**
     * Human-readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Medical => 'Médical',
            self::Paramedical => 'Paramédical',
            self::Soins => 'Soins',
            self::Assistance => 'Assistance',
            self::MaternitePediatrie => 'Maternité & Pédiatrie',
        };
    }

    /**
     * Description of the category.
     */
    public function description(): string
    {
        return match ($this) {
            self::Medical => 'Consultations médicales générales et pédiatriques.',
            self::Paramedical => 'Rééducation physique, kinésithérapie et soins obstétriques.',
            self::Soins => 'Soins infirmiers, prélèvements et pansements.',
            self::Assistance => 'Aide à la mobilité, hygiène et garde-malade.',
            self::MaternitePediatrie => 'Accompagnement de la mère, du nourrisson et de l\'enfant.',
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
     * Return all categories structured for API responses and select dropdowns.
     *
     * @return list<array{id: string, value: string, name: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $cat) => [
            'id' => $cat->value,
            'value' => $cat->value,
            'name' => $cat->label(),
            'label' => $cat->label(),
            'description' => $cat->description(),
        ], self::cases());
    }
}

