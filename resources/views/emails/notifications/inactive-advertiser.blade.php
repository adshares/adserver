@component('mail::message')
Dear Advertiser, 

Welcome to {{ config('app.adserver_name') }}! We noticed you haven't created your first campaign yet.

Click [here]({{ $dashboardUrl }}) to start setting up your campaign.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

We're excited to help you reach your advertising goals!

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
