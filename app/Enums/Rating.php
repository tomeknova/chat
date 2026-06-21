<?php

namespace App\Enums;

enum Rating: string
{
    case Up = 'up';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Pomocne',
            self::Down => 'Niepomocne',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Up => 'success',
            self::Down => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Up => 'heroicon-o-hand-thumb-up',
            self::Down => 'heroicon-o-hand-thumb-down',
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
