@component('mail::message')
Hello,

The panel placeholders were changed on {{ $date }}. Make sure the changes were yours.

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
