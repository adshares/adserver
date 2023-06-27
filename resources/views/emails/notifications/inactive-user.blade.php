@component('mail::message')
Dear User, 

Welcome to {{ config('app.adserver_name') }}! We noticed you haven't created your first campaign or added your website yet.

Click [here]({{ $advertiserDashboardUrl }}) to start setting up your campaign
or [here]({{ $publisherDashboardUrl }}) to add your website and start monetizing your content with our advertising solutions.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

We're excited to work with you!

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
