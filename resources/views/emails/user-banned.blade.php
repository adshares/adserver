@component('mail::message')
# Account banned

Your account has been banned due to: {{ $reason }}.
If you want to explain, contact support {{ config('app.support_email') }}

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
