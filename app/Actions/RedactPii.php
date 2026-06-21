<?php

namespace App\Actions;

/**
 * Action: RedactPii
 *
 * Masks obvious personal data before a user question is stored (SCOPE_V1:
 * "PII redacted-only — raw question is NOT kept"). Best-effort, conservative:
 * e-mails, phone numbers and long digit sequences. The corpus answer-units are
 * never affected — this only touches what the user typed.
 */
class RedactPii
{
    public function handle(string $text): string
    {
        // E-mail addresses.
        $text = preg_replace('/[\p{L}0-9._%+\-]+@[\p{L}0-9.\-]+\.[\p{L}]{2,}/u', '[email]', $text) ?? $text;

        // Phone numbers (optional +, 9+ digits with spaces/dashes/parens).
        $text = preg_replace('/(?<!\d)(\+?\d[\d\s().\-]{7,}\d)(?!\d)/', '[telefon]', $text) ?? $text;

        // Any remaining long digit run (IDs, account numbers).
        $text = preg_replace('/\d{9,}/', '[numer]', $text) ?? $text;

        return trim($text);
    }
}
