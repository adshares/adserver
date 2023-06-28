@component('mail::message')
Dear Publisher,

We noticed that your
@switch($mediumName)
    @case('web')
        website
        @break
    @case('metaverse')
        Metaverse site
        @break
    @default
        site
@endswitch
is currently saved as a draft. 

To start monetizing your
@switch($mediumName)
    @case('web')
        digital content,
        @break
    @case('metaverse')
        digital land,
        @break
    @default
        site,
@endswitch
click [here]({{ $dashboardUrl }}).
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
