@component('mail::message')
# Confirm withdrawal

Please confirm your withdrawal request.
- Recipient Address: {{ $target }}
- Amount: {{ $amount }} ADS
- Fee: {{ $fee }} ADS
- **TOTAL**: {{ $total }} ADS

@component('mail::button', ['url' => $url])
Confirm Withdrawal
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
