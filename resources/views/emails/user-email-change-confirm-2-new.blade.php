@component('mail::message')
# Confirm your new email

{{-- Dear {{ $name }} --}}

Please confirm this is your new email that you want to link with your Adshares account

@component('mail::button', ['url' => env('ADPANEL_URL').$uri.$token])
Confirm and save new email
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
