@component('mail::message')
# File ads.txt on site "{{ $siteName }}" is invalid

Please check ads.txt file on site "{{ $siteName }}". It is not accessible on expected url ({{ $adsTxtUrl }}) or does not contain expected entry.

<code>
    {{ $adsTxtEntry }}
</code>

For more information please visit documentation page <a href="https://adshar.es/adstxt" target="_blank">https://adshar.es/adstxt</a>.

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
