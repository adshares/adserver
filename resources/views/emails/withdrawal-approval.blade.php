@component('mail::message')
    # Confirm email change request

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
