<?php

namespace App\Enums;

/**
 * Product-facing outcome of an assistant message (what the user effectively got).
 */
enum ProductStatus: string
{
    case Answered = 'answered';
    case Abstained = 'abstained';
    case NeedsClarification = 'needs_clarification';

    public function label(): string
    {
        return match ($this) {
            self::Answered => 'Odpowiedziano',
            self::Abstained => 'Wstrzymano',
            self::NeedsClarification => 'Wymaga doprecyzowania',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Answered => 'success',
            self::Abstained => 'warning',
            self::NeedsClarification => 'info',
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
