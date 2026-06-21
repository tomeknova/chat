<?php

namespace App\Enums;

/**
 * Strict-JSON response type returned by the model (operational, pre-validation).
 */
enum ResponseType: string
{
    case Answer = 'answer';
    case Clarification = 'clarification';
    case Abstention = 'abstention';
    case OutOfScope = 'out_of_scope';

    public function label(): string
    {
        return match ($this) {
            self::Answer => 'Odpowiedź',
            self::Clarification => 'Doprecyzowanie',
            self::Abstention => 'Wstrzymanie',
            self::OutOfScope => 'Poza zakresem',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Answer => 'success',
            self::Clarification => 'info',
            self::Abstention => 'warning',
            self::OutOfScope => 'gray',
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
