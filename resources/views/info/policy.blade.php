<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title></title>
</head>
<body>
@if (isset($content))
{!! $content !!}
@else
Contact support {{ config('app.adshares_support_email') }}
@endif
</body>
</html>
