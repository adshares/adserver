@component('mail::message')
# Account Activation

{{-- Dear {{ $name }} --}}

Thank you for registering with Adshares. Please click button below to activate your account

{{-- @component('mail::button', ['url' => route('registerActivate',[$hash])])
Accept and Activate
@endcomponent --}}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
