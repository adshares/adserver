@component('mail::message')
# Confirm withdrawal

Please confirm your withdrawal request.
- Recipient Address: {{ $target }}
- Amount: {{ $amount }}
- Fee: {{ $fee }}
- **TOTAL**: {{ $fee + $amount }}

@component('mail::button', ['url' => $url])
Confirm Withdrawal
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
