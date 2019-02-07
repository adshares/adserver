@component('mail::message')
    ## Not enough founds on your account.

    Please transfer {{ $transferValue }} clicks from Cold Wallet to Hot Wallet - {{ $address }}.

    Thanks,

    {{ config('app.name') }} Team
@endcomponent
