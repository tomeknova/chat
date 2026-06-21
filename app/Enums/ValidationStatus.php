<?php

namespace App\Enums;

/**
 * Result of validating a model-selected answer-unit against the generation
 * context (∈ context + content_hash). Only `accepted` units are rendered.
 */
enum ValidationStatus: string
{
    case Accepted = 'accepted';
    case RejectedUnknownUnit = 'rejected_unknown_unit';
    case RejectedHashMismatch = 'rejected_hash_mismatch';
    case RejectedInjection = 'rejected_injection';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Zaakceptowana',
            self::RejectedUnknownUnit => 'Odrzucona: nieznana jednostka',
            self::RejectedHashMismatch => 'Odrzucona: niezgodny hash',
            self::RejectedInjection => 'Odrzucona: injection',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            default => 'danger',
        };
    }

    public function isAccepted(): bool
    {
        return $this === self::Accepted;
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
