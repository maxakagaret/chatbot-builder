<?php

namespace App\Chatbot\Engine;

/** Validates and normalises free-text answers of input steps. */
class InputValidator
{
    public const TYPES = ['text', 'email', 'phone', 'number'];

    /** @return string|null the normalised value, or null if the answer is invalid */
    public function validate(string $type, string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_strtolower($value) : null,
            'phone' => $this->phone($value),
            'number' => is_numeric(str_replace(',', '.', $value)) ? str_replace(',', '.', $value) : null,
            default => mb_substr($value, 0, 500),
        };
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return strlen($digits) >= 7 && strlen($digits) <= 15 ? '+'.$digits : null;
    }
}
