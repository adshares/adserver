@component('mail::message')
# Confirm password set request

Please confirm your password set request

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
Confirm password set request
@endcomponent

Thanks,

{{ config('app.name') }} Team
@endcomponent
