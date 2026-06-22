<?php

namespace App\Enums;

/**
 * Generation lifecycle (decision R) — distinct from ProductStatus (domain
 * outcome) and InfraStatus (final transport result). Drives the reservation:
 * one row + at most one active executor, with lease-based CAS takeover.
 */
enum ProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Oczekuje',
            self::Processing => 'W toku',
            self::Completed => 'Zakończone',
            self::Failed => 'Nieudane',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Processing, self::Pending => 'warning',
            self::Failed => 'danger',
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
