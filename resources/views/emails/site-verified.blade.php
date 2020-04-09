@component('mail::message')
Hello,

@if (1 == count($sites))
Your site <a href="{{ $sites[0]['url'] }}" target="_blank">{{ $sites[0]['name'] }}</a> was verified.
@else
Your sites were verified:
<ul>
    @foreach($sites as $site)
        <li>
            <a href="{{ $site['url'] }}" target="_blank">{{ $site['name'] }}</a>
        </li>
    @endforeach
</ul>
@endif

Thanks,

{{ config('app.name') }} Team
@endcomponent
