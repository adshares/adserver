{!! $body !!}

@isset($unsubscribe_url)
    <div style="background:#e7e7e7;color:black;font-family:Arial,sans-serif;line-height: 28px;font-size: 12px">
        This is notification from {{ config('app.name') }} Team.
        <a href="{{ $unsubscribe_url }}" target="_blank">Unsubscribe</a>
    </div>
@endisset
