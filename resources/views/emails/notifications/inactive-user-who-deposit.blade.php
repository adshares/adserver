@component('mail::message')
Dear Advertiser,

Welcome to {{ config('app.adserver_name') }}! We noticed that you've successfully credited your account but haven't launched your first campaign yet.

Get started today! Click [here]({{ $dashboardUrl }}) to create and launch your campaign.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Let's make the most of your advertising goals together!

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
