<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E-mail, na ktorý sa dá reálne doručiť.
 *
 * Samotné pravidlo `email` pustí aj „jana@gmail“ alebo „x@y“ – podľa RFC sú
 * platné, no potvrdenie na ne nikdy nepríde. Preto navyše vyžadujeme doménu
 * s bodkou a aspoň dvojpísmennou koncovkou.
 *
 * DNS kontrolu (email:dns) zámerne nerobíme: pri výpadku DNS by formulár
 * odmietal aj správne adresy a testy by záviseli od siete.
 */
class DeliverableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? trim($value) : $value;

        $valid = is_string($value)
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/@[^@\s]+\.\p{L}{2,}$/u', $value) === 1;

        if (! $valid) {
            $fail('Pole :attribute musí obsahovať platnú e-mailovú adresu (napr. jana.novakova@gmail.com).');
        }
    }
}
