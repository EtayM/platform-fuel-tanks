<?php

namespace Enjin\Platform\FuelTanks\Rules;

use Closure;
use Enjin\Platform\FuelTanks\Models\FuelTank;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Contracts\Validation\ValidationRule;

class IsFuelTankOwner implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fuelTank = FuelTank::with('owner')->firstWhere('public_key', SS58Address::getPublicKey($value));
        if (!$fuelTank) {
            $fail(__('validation.exists', ['attribute' => $attribute]))->translate();

            return;
        }

        if (!SS58Address::isSameAddress($fuelTank->owner->public_key, SS58Address::getDaemonAccount())) {
            $fail('enjin-platform-fuel-tanks::validation.is_fuel_tank_owner')->translate();
        }
    }
}
