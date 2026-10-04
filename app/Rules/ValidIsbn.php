<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIsbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $isbn = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');

        if (strlen($isbn) === 10 && $this->validIsbn10($isbn)) {
            return;
        }

        if (strlen($isbn) === 13 && $this->validIsbn13($isbn)) {
            return;
        }

        $fail('ISBN tidak valid.');
    }

    private function validIsbn10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
            $sum += $digit * (10 - $index);
        }

        return $sum % 11 === 0;
    }

    private function validIsbn13(string $isbn): bool
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 13; $index++) {
            $digit = (int) $isbn[$index];
            $sum += $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
