@component('mail::message')
Dear Publisher,

Your @switch($mediumName)
@case('web')
website @break
@case('metaverse')
Metaverse site @break
@default
site @endswitch
**{{ $siteName }}** has been verified and accepted.

You can now start monetizing your @switch($mediumName)
@case('web')
digital content. @break
@case('metaverse')
digital land. @break
@default
site. @endswitch
Log in [here]({{ $siteUrl }}) to manage your account, track your earnings, or get insights to grow your revenue.
Need help? Feel free to email us at [{{ $contactEmail }}](mailto:{{ $contactEmail }})
@if($bookingUrl)
or set up a meeting with one of our consultants: [BOOKING LINK]({{ $bookingUrl }})
@endif

Best regards, {{ config('app.adserver_name') }} Team
@endcomponent
