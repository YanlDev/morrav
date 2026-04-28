<?php

namespace App\Enums;

enum DamageReason: string
{
    case Broken = 'broken';
    case Stained = 'stained';
    case Defective = 'defective';
    case Incomplete = 'incomplete';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Broken => 'Roto',
            self::Stained => 'Manchado',
            self::Defective => 'Defectuoso',
            self::Incomplete => 'Incompleto',
            self::Other => 'Otro',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
