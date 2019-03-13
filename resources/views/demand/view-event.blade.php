<html>
    <head>
        <script src="{{ $view_script_url }}"></script>
    </head>
    <body>
        <script type="text/javascript">
            demandLogContext('{{ $log_url }}');
            @isset($aduser_url)
            parent.postMessage({
                'adsharesTrack': [
                    {'type': 'iframe', 'url': '{{ $aduser_url }}'}
                ]
            }, '*');
            @endisset
        </script>
    </body>
</html>
