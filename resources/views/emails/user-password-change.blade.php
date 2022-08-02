@component('mail::message')
# Password change

The password to your account was changed.
If you are not responsible for this change, contact support immediately {{ config('app.support_email') }}

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
