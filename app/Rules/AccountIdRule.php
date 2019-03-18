<?php

namespace Adshares\Adserver\Rules;

use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Contracts\Validation\Rule;

class AccountIdRule implements Rule
{
    /** @var array */
    private $blacklistedAccountIds;

    /** @var AccountId */
    private $blacklistedAccountId;

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
            return false;
        }

        $accountId = new AccountId($value, false);

        foreach ($this->blacklistedAccountIds as $blacklistedAccountId) {
            if ($accountId->equals($blacklistedAccountId)) {
                $this->blacklistedAccountId = $blacklistedAccountId;

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
    public function message(): string
    {
        if (null !== $this->blacklistedAccountId) {
            return sprintf(
                'The :attribute must be different than blacklisted (%s)',
                $this->blacklistedAccountId->toString()
            );
        }

        return 'The :attribute must be valid AccountId.';
    }
}
