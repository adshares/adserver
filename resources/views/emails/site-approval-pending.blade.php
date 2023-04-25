@component('mail::message')
# Site acceptance is pending

Hello,

User {{ $user }} added <a href="{{ $url }}" target="_blank">site {{ $url }}</a> which needs approval.

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
