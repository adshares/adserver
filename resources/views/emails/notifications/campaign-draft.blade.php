@component('mail::message')
Dear Advertiser, 

We noticed that your campaign, "{{ $campaignName }}" is currently saved as a draft.

To publish your campaign and start reaching your target audience, click [here]({{ $campaignUrl }}).
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
