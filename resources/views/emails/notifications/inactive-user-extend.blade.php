@component('mail::message')
Dear User, 

It seems like it's been a while since we've seen you.
There's so much waiting for you at {{ config('app.adserver_name') }}. 

Click [here]({{ $panelUrl }}) to access your account.
Remember, if you need any assistance or have questions, feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

We look forward to seeing you again!

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent

 
