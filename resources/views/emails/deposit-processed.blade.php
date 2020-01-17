@component('mail::message')
Hello,

We would like to inform you that {{ $amount }} ADS has been deposited into your account.
In order to check your account, please log in to the platform, select “Billing & payments” from the dropdown menu in the upper right corner.


Thanks,

{{ config('app.name') }} Team
@endcomponent
