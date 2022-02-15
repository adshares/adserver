@component('mail::message')
# Password change

The password to your account was changed.
If you are not responsible for this change, contact support immediately {{ config('app.adshares_support_email') }}

Thanks,

{{ config('app.name') }} Team
@endcomponent
