@component('mail::message')
# Confirm cryptocurrency wallet connection request

Please confirm you want to connect your Adshares account to this cryptocurrency wallet:

- Wallet address: {{ $address }}
- Wallet network: {{ $network }}

@component('mail::button', ['url' => config('app.adpanel_url').$uri.$token])
    Confirm wallet connection
@endcomponent

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
