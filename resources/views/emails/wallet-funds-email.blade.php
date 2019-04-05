@component('mail::message')
## Not enough funds on your account.

Please transfer {{ $transferValue }} ADS from Cold Wallet to Hot Wallet - {{ $address }}.

Thanks,

{{ config('app.name') }} Team
@endcomponent
