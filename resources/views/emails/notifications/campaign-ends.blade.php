@component('mail::message')
Dear Advertiser, 

Your campaign, "{{ $campaignName }}" will conclude in three days. 

Review campaign results or download your report [here]({{ $campaignUrl }}). 
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
