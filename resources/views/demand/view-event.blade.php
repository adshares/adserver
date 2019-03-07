<html>
    <head>
        <script src="{{ $view_script_url }}"></script>
    </head>
    <body>
        <script type="text/javascript">
            demandLogContext('{{ $log_url }}');
        </script>
    @isset($aduser_url)
        <iframe src="{{ $aduser_url }}" sandbox="allow-scripts allow-same-origin"></iframe>
    @endisset
    </body>
</html>