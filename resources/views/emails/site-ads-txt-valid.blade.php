@component('mail::message')
# File ads.txt on site "{{ $siteName }}" is correct

Your ads.txt file is correct. You can now earn using {{ config('app.adserver_name') }}.

Thanks,

{{ config('app.adserver_name') }} Team
@endcomponent
