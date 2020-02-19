@component('mail::message')
# Confirm withdrawal

Please confirm your withdrawal request.
- Recipient Address: {{ $target }}
- Currency: {{ $currency }}
- Amount: {{ $amount }} ADS
@if ($fee > 0)
- Fee: {{ $fee }} ADS
- **TOTAL**: {{ $total }} ADS
@endif

@component('mail::button', ['url' => $url])
Confirm Withdrawal
@endcomponent

Thanks,

{{ config('app.name') }} Team
@endcomponent
