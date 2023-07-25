@component('mail::message')
Dear Advertiser,

We've noticed your account balance has been depleted.

To avoid disruption to your campaigns, please top up your account [here]({{ $depositUrl }}).
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
