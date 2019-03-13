<?php

namespace Adshares\Adserver\Rules;

use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Contracts\Validation\Rule;

class AccountIdRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return AccountId::isValid($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be valid AccountId.';
    }
}
