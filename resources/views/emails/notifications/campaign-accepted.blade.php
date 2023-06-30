@component('mail::message')
Dear Advertiser,

Your campaign, "{{ $campaignName }}", has been verified and accepted.
@if(!$allBannersAccepted)
However, certain banners didn't meet our guidelines due to quality and thematic issues.
@endif

Log in [here]{{ $campaignUrl }} to view your campaign details, make any final adjustments, or monitor its progress.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards,
{{ config('app.adserver_name') }} Team
@endcomponent
