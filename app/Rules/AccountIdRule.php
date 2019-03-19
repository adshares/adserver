<?php

namespace Adshares\Adserver\Rules;

use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Contracts\Validation\Rule;

class AccountIdRule implements Rule
{
    /** @var array */
    private $blacklistedAccountIds;

    /** @var string|null */
    private $message;

    public function __construct(array $blacklistedAccountIds = [])
    {
        $this->blacklistedAccountIds = $blacklistedAccountIds;
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
        if (!AccountId::isValid($value)) {
            $this->message = 'The :attribute must be valid AccountId.';

            return false;
        }

        $accountId = new AccountId($value, false);

        foreach ($this->blacklistedAccountIds as $blacklistedAccountId) {
            if ($accountId->equals($blacklistedAccountId)) {
                $this->message = 'The :attribute must be different than blacklisted (:input)';

                return false;
            }
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
