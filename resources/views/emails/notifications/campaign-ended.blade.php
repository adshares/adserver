@component('mail::message')
Dear Advertiser,

Your campaign, "{{ $campaignName }}" has successfully concluded.

Log in [here]({{ $campaignUrl }}) to review its performance, download your report, or start planning your next campaign.
If you have any questions or need assistance, please don't hesitate to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Thank you for choosing **{{ config('app.adserver_name') }}** for your advertising needs! 

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
