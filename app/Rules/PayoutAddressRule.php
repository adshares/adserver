<?php

namespace Adshares\Adserver\Rules;

use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Contracts\Validation\Rule;

class PayoutAddressRule implements Rule
{
    /** @var string|null */
    private $message;

    public function __construct()
    {

    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!WalletAddress::isValid($value)) {
            $this->message = 'The :attribute must be valid PayoutAddress.';

            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): ?string
    {
        return $this->message;
    }
}
