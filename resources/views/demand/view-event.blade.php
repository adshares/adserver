<html>
    <head>
        <script src="{{ $view_script_url }}"></script>
    </head>
    <body>
        <script type="text/javascript">
            @isset($aduser_url)
            parent.postMessage({
                'insertElem': [
                    {'type': 'iframe', 'url': '{{ $aduser_url }}'}
                ]
            }, '*');
            @endisset
            demandLogContext('{{ $log_url }}');
        </script>
    </body>
</html>
