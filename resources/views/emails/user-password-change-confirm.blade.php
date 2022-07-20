@component('mail::message')
# Confirm password change request

Please confirm your password change request

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
Confirm password change
@endcomponent

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
