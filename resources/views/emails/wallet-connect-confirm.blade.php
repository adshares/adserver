@component('mail::message')
# Confirm cryptocurrency wallet connect request

Please confirm you want to connect your Adshares account to this cryptocurrency wallet:

- Wallet address: {{ $address }}
- Wallet network: {{ $network }}

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
    Confirm wallet connect request
@endcomponent

Thanks,

{{ config('app.name') }} Team
@endcomponent
