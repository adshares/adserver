@component('mail::message')
# Your cryptocurrency wallet has been successfully connected

- Wallet address: {{ $address }}
- Wallet network: {{ $network }}

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
