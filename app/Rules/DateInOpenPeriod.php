<?php

namespace App\Rules;

use App\Models\FinancialPeriod;
use Illuminate\Contracts\Validation\Rule;

class DateInOpenPeriod implements Rule
{
    public function passes($attribute, $value): bool
    {
        try {
            return FinancialPeriod::isOpen($value);
        } catch (\Exception $e) {
            return true;
        }
    }

    public function message(): string
    {
        return 'The period for this date is closed. Reopen the period before posting.';
    }
}
