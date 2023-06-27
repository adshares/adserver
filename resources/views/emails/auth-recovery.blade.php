@component('mail::message')
# Password Recovery

Please use button below to set up your new password

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
Set up new password
@endcomponent

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
