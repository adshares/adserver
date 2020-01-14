@component('mail::message')
# Password Recovery

{{-- Dear {{ $name }} --}}

Please use button below to setup your new password

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
Setup new password
@endcomponent

Thanks,

{{ config('app.name') }} Team
@endcomponent
