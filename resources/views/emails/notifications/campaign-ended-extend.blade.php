@component('mail::message')
Dear Advertiser,

We've noticed it's been a few weeks since your last campaign and we haven't seen you around.

Your audience is waiting, and there are new opportunities to reach them!
Log back in [here]({{ $dashboardUrl }}) to start your next successful campaign.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

We look forward to your return! 

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
