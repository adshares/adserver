@component('mail::message')
# Password Recovery

{{-- Dear {{ $name }} --}}

Please use button below to setup your new password

@component('mail::button', ['url' => env('ADPANEL_URL').$uri.$token])
Setup new password
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
