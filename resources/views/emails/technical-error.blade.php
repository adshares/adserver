@component('mail::message')
# Technical Error: <span style="color: #ff414d">{{ $title }}</span>

{{ $message }}

Do not ignore this error because it causes <span style="font-weight: bold">a serious problem</span> on your server.

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
