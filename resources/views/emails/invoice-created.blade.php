@component('mail::message')
Hello,

Your invoice has been attached to the message. You can also download it from this link:
{{ $invoice->downloadUrl }}

Thanks,

{{ config('app.name') }} Team
@endcomponent
