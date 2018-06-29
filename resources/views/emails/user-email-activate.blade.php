@component('mail::message')
# Account Activation

{{-- Dear {{ $name }} --}}

Thank you for registering with Adshares. Please click button below to activate your account

@component('mail::button', ['url' => env('ADPANEL_URL').'/auth/register-confirm/'.$hash])
Accept and Activate
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
