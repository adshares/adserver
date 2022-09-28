@component('mail::message')
Hello,

We would like to inform you that {{ $amount }} {{ $currency }} has been deposited into your account.
In order to check your account, please log in to the platform, select “Billing & payments” from the dropdown menu in the upper right corner.


Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
