@component('mail::message')
Dear Publisher, 

Welcome to {{ config('app.adserver_name') }}! We noticed that you haven't added your website yet.

Click [here]({{ $dashboardUrl }}) to add your website and start monetizing your content with our advertising solutions.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

We're excited to work with you!

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
