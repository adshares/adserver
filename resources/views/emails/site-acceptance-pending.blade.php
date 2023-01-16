@component('mail::message')
#Site acceptance is pending

Hello,

User {{ $user }} requested <a href="{{ $url }}" target="_blank">site {{ $url }}</a> acceptance.

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
