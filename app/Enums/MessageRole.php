<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Użytkownik',
            self::Assistant => 'Asystent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Assistant => 'info',
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
