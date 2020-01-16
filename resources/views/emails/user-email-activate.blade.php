@component('mail::message')
# Account Activation

{{-- Dear {{ $name }} --}}

Thank you for registering with Adshares. Please click button below to activate your account

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
Accept and Activate
@endcomponent

Thanks,

{{ config('app.name') }} Team
@endcomponent
