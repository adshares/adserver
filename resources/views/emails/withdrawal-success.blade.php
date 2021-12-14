@component('mail::message')
# Withdrawal success

Withdrawal order has been added.
- Recipient Address: {{ $target }}
- Currency: {{ $currency }}
- Amount: {{ $amount }} ADS
@if ($fee > 0)
- Fee: {{ $fee }} ADS
- **TOTAL**: {{ $total }} ADS
@endif

Thanks,

{{ config('app.name') }} Team
@endcomponent
