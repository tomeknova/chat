<?php

namespace App\Enums;

/**
 * Technical outcome of a single OpenRouter generation (infra-level, not product).
 * More states may be added as safeguards land (SCOPE_V1 lists these explicitly).
 */
enum InfraStatus: string
{
    case Completed = 'completed';
    case InvalidSchema = 'invalid_schema';
    case ProviderTimeout = 'provider_timeout';
    case ProviderRefusal = 'provider_refusal';
    case CorpusIntegrityError = 'corpus_integrity_error';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Zakończone',
            self::InvalidSchema => 'Niepoprawny schemat',
            self::ProviderTimeout => 'Timeout dostawcy',
            self::ProviderRefusal => 'Odmowa dostawcy',
            self::CorpusIntegrityError => 'Błąd integralności korpusu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            default => 'danger',
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
