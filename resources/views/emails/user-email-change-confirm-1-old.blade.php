@component('mail::message')
# Confirm email change request

{{-- Dear {{ $name }} --}}

Please confirm your email change request, once confirmed you will receive another confirmation email on your new email account

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
Confirm email change request
@endcomponent

Thanks,

{{ config('app.name') }} Team
@endcomponent
