@component('mail::message')
Hello,

We would like to inform you that all funds on your account have been used.
Your campaigns have been suspended until new funds are deposited.
In order to deposit ADS coins into your account, please log in to the platform, select “Add funds” from the dropdown menu in the upper right corner and send a transfer using the provided account address and the message.
You can transfer ADS coins either form your ADS wallet or from an exchange.
Once there are sufficient funds on your account, your campaigns will be automatically resumed and you will get an e-mail notification.

Thanks,

{{ config('app.name') }} Team
@endcomponent
