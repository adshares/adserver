@component('mail::message')
Dear Advertiser, 

We've noticed your account balance is nearing depletion.

To avoid interruption to your campaigns, consider adding funds to your account [here]({{ $depositUrl }}).
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
